#!/usr/bin/env bash
#
# Repeatable production deployment. Run on the server, in the application folder, as the user that owns it:
#
#   deploy/deploy.sh [git-ref]        # default: origin/main. Use a tag in production.
#   HEALTH_URL=https://erp.azanafarms.com/health deploy/deploy.sh v1.4.0
#
# Order, and why:
#   1. checks before anything changes: .env present, clean working tree, the ref exists, no other deployment running
#   2. takes a database backup and PROVES it (a fresh, successful, non-empty run, copied off-site) - no verified backup, no deployment
#   3. maintenance mode, checkout, dependencies, assets, migrations, roles, caches, preflight
#   4. workers restarted, site brought up, /health checked
#
# If anything fails after maintenance mode starts, the script puts the previous release back and brings the site up again:
#   - it undoes the migrations this run applied (every migration in the project is reversible),
#   - checks out the previous commit, reinstalls and rebuilds it, and goes live.
# The site stays down ONLY if that recovery itself fails (for example a migration that cannot be reversed). It then says so, loudly, and
# says what to do: serving old code on a half-migrated database would be worse than a banner. Rolling back by hand: docs/DEPLOYMENT.md.
set -Eeuo pipefail

REF="${1:-origin/main}"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
HEALTH_URL="${HEALTH_URL:-}"            # e.g. https://erp.azanafarms.com/health ; empty skips the final check
BACKUP_MAX_AGE_MINUTES="${BACKUP_MAX_AGE_MINUTES:-15}"

cd "$(dirname "$0")/.."

say()  { echo "==> $*"; }
fail() { echo "ERROR: $*" >&2; exit 1; }

DOWN=0          # 1 once this script has put the site in maintenance mode
DONE=0          # 1 once the site is live again on the new release
RAN_BEFORE=""   # migrations applied before this run
PREVIOUS=""

ran_count() { $PHP artisan migrate:status --no-ansi 2>/dev/null | grep -c -E '\] Ran[[:space:]]*$' || true; }

# --------------------------------------------------------------------------------------------------------------------------- recovery
install_release() {
    $COMPOSER install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    npm ci --no-audit --no-fund --ignore-scripts
    npm run build
    $PHP artisan storage:link 2>/dev/null || true
    $PHP artisan optimize:clear
    $PHP artisan config:cache
    $PHP artisan route:cache
    $PHP artisan view:cache
    $PHP artisan event:cache
    $PHP artisan filament:optimize 2>/dev/null || true
}

recover() {
    local code=$?
    trap - ERR EXIT INT TERM
    set +e
    [[ $DONE -eq 1 || $DOWN -eq 0 ]] && exit "$code"

    echo >&2
    echo "!! The deployment failed (exit $code). Putting the previous release back so the site is not left in maintenance mode." >&2

    if [[ -n "$RAN_BEFORE" ]]; then
        local steps=$(( $(ran_count) - RAN_BEFORE ))
        if (( steps > 0 )); then
            echo "!! Undoing $steps migration(s) applied by this run" >&2
            $PHP artisan migrate:rollback --step="$steps" --force || stuck "The migrations could not be undone."
        fi
    fi

    if [[ -n "$PREVIOUS" ]]; then
        git checkout --detach "$PREVIOUS" || stuck "Could not check out $PREVIOUS."
        ( install_release ) || stuck "The previous release could not be rebuilt."
        $PHP artisan queue:restart || true
    fi

    $PHP artisan up || stuck "Could not leave maintenance mode."
    echo "!! The site is back on ${PREVIOUS:0:7} (before this deployment). Nothing of the failed release is live. Find the cause above and try again." >&2
    exit "$code"
}

stuck() {
    cat >&2 <<EOF

!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
!! RECOVERY FAILED: $1
!! The site is STILL IN MAINTENANCE MODE on purpose: the code and the database may not match.
!! Do this, in order (docs/DEPLOYMENT.md, "Rolling back"):
!!   1. read the messages above to see which step failed
!!   2. fix it, or restore the verified backup taken at the start of this run (docs/BACKUP_AND_RESTORE.md)
!!   3. git checkout --detach ${PREVIOUS:-<previous commit>}; composer install --no-dev -o; npm ci && npm run build
!!   4. php artisan optimize:clear; php artisan config:cache; php artisan queue:restart; php artisan up
!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
EOF
    trap - ERR EXIT INT TERM
    exit 1
}

trap recover ERR
trap 'exit 130' INT TERM
trap recover EXIT

# --------------------------------------------------------------------------------------------------------------------------- 1. checks
[[ -f .env ]] || fail "No .env here. Copy deploy/env.production.example to .env and fill it in first."

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    fail "The working tree has uncommitted changes. A deployment must be exactly a git ref."
fi

if command -v flock >/dev/null 2>&1; then
    mkdir -p storage/framework
    exec 9>storage/framework/deploy.lock
    flock -n 9 || fail "Another deployment is already running."
fi

say "Fetching $REF"
git fetch --tags --prune origin
TARGET="$(git rev-parse --verify --quiet "${REF}^{commit}")" || fail "'$REF' is not a known commit, tag or branch."
PREVIOUS="$(git rev-parse HEAD)"
echo "    ${PREVIOUS:0:7} -> ${TARGET:0:7}"

# --------------------------------------------------------------------------------------------------------------------------- 2. backup
say "Backing up the database"
$PHP artisan erp:backup --no-prune || fail "The backup failed: not deploying."

say "Checking the backup is real"
VERIFY="$($PHP artisan tinker --no-ansi --execute='
$r = App\Domain\Backup\Models\BackupRun::where("kind", "backup")->latest("started_at")->first();
$offsite = (bool) config("backup.offsite_disk");
echo ($r && $r->status === "success" && $r->size_bytes > 0 && filled($r->checksum) && $r->started_at->gt(now()->subMinutes('"$BACKUP_MAX_AGE_MINUTES"'))
    && (! $offsite || $r->offsite)) ? "BACKUP_VERIFIED" : "BACKUP_NOT_VERIFIED";' 2>/dev/null || true)"
[[ "$VERIFY" == *BACKUP_VERIFIED* ]] || fail "No fresh, successful, checksummed backup copied off-site was found: not deploying. (Look at Administration > Backups & monitoring.)"
echo "    verified"

# --------------------------------------------------------------------------------------------------------------------------- 3. deploy
RAN_BEFORE="$(ran_count)"

say "Maintenance mode on"
# An already-down site is fine; a failure to go down is not (the migration would run under live traffic).
if ! $PHP artisan down --retry=60 --refresh=15; then
    [[ -f storage/framework/down ]] || fail "Could not enable maintenance mode: not deploying."
fi
DOWN=1

say "Checking out ${TARGET:0:7}"
git checkout --detach "$TARGET"

say "Installing dependencies and building assets"
$COMPOSER install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund --ignore-scripts
npm run build

say "Pending migrations"
$PHP artisan migrate:status --pending --no-ansi || true
say "Migrating"
$PHP artisan migrate --force --isolated

say "Refreshing roles and permissions (new modules; existing grants are never overwritten)"
$PHP artisan db:seed --class='Database\Seeders\RoleSeeder' --force

say "Caching"
$PHP artisan storage:link 2>/dev/null || true
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan filament:optimize 2>/dev/null || true

say "Preflight"
$PHP artisan erp:preflight

# --------------------------------------------------------------------------------------------------------------------------- 4. live
say "Restarting workers and going live"
$PHP artisan queue:restart
$PHP artisan up
DOWN=0
DONE=1
trap - ERR EXIT

if [[ -n "$HEALTH_URL" ]]; then
    say "Health check"
    for attempt in 1 2 3 4 5; do
        if curl -fsS --max-time 10 "$HEALTH_URL" > /dev/null; then echo "    healthy"; echo "Deployed ${TARGET:0:7} (was ${PREVIOUS:0:7})."; exit 0; fi
        sleep 3
    done
    echo "The site is UP but $HEALTH_URL is not healthy. Investigate, or roll back to ${PREVIOUS:0:7} (docs/DEPLOYMENT.md)." >&2
    exit 1
fi

echo "Deployed ${TARGET:0:7} (was ${PREVIOUS:0:7})."
