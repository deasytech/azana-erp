#!/usr/bin/env bash
#
# Repeatable production deployment. Run on the server, in the application folder, as the user that owns it:
#
#   deploy/deploy.sh [git-ref]        # default: origin/main
#
# What it does, in order, and why:
#   1. refuses to run with uncommitted changes (a deployment must be exactly a git ref)
#   2. takes a database backup BEFORE touching anything, so there is always a way back
#   3. puts the site in maintenance mode, fetches the ref, installs dependencies, builds assets
#   4. migrates, refreshes roles/permissions (never overwrites edits), caches configuration
#   5. runs the preflight checks; if any fails, the site STAYS in maintenance mode and the script stops
#   6. restarts the queue workers, brings the site up, and checks /health
#
# Rolling back: see docs/DEPLOYMENT.md ("Rolling back").
set -euo pipefail

REF="${1:-origin/main}"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
HEALTH_URL="${HEALTH_URL:-}"   # e.g. https://erp.azanafarms.com/health ; empty skips the final check

cd "$(dirname "$0")/.."

[ -f .env ] || { echo "No .env here. Copy deploy/env.production.example to .env and fill it in first." >&2; exit 1; }

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "The working tree has uncommitted changes. A deployment must be exactly a git ref." >&2
    exit 1
fi

echo "==> Backing up the database"
$PHP artisan erp:backup --no-prune || { echo "Backup failed: not deploying." >&2; exit 1; }

echo "==> Maintenance mode on"
$PHP artisan down --retry=60 --refresh=15 || true
trap 'echo "Deployment stopped. The site is still in maintenance mode; fix the problem and re-run, or follow the rollback steps." >&2' ERR

echo "==> Fetching $REF"
git fetch --tags --prune origin
PREVIOUS="$(git rev-parse HEAD)"
git checkout --detach "$REF"
echo "    $PREVIOUS -> $(git rev-parse HEAD)"

echo "==> Installing dependencies"
$COMPOSER install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build

echo "==> Migrating"
$PHP artisan migrate --force

echo "==> Refreshing roles and permissions (new modules; existing grants are never overwritten)"
$PHP artisan db:seed --class=Database\\Seeders\\RoleSeeder --force

echo "==> Caching"
$PHP artisan storage:link 2>/dev/null || true
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan filament:optimize 2>/dev/null || true

echo "==> Preflight"
$PHP artisan erp:preflight

echo "==> Restarting workers and going live"
$PHP artisan queue:restart
$PHP artisan up
trap - ERR

if [ -n "$HEALTH_URL" ]; then
    echo "==> Health check"
    for attempt in 1 2 3 4 5; do
        if curl -fsS --max-time 10 "$HEALTH_URL" > /dev/null; then echo "    healthy"; exit 0; fi
        sleep 3
    done
    echo "The site is up but $HEALTH_URL is not healthy. Investigate, or roll back (docs/DEPLOYMENT.md)." >&2
    exit 1
fi

echo "Deployed $(git rev-parse --short HEAD) (was ${PREVIOUS:0:7})."
