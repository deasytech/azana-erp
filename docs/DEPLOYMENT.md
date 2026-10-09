# Deployment

One Laravel application on one server, served as `www.azanafarms.com` (public website) and `erp.azanafarms.com` (management application), sharing one database (docs/ARCHITECTURE.md). `WEBSITE_HOST` and `ERP_HOST` in `.env` decide which pages each address serves.

## What the server needs

- Ubuntu LTS (or similar), nginx, PHP 8.3 FPM with `bcmath mbstring intl zip gd pdo_mysql zlib`, MySQL 8 (or MariaDB), Composer, Node 20+, Supervisor, certbot, `gzip`, `git`, MySQL client tools (`mysql`, `mysqldump`).
- Files in `deploy/`: `nginx/azana.conf`, `supervisor/azana-worker.conf`, `crontab.example`, `env.production.example`, `deploy.sh`.

## First installation

1. Create the database and a dedicated MySQL user (not root). Give the user rights on the database and on the restore-test scratch database (`azana_restore_test`).
2. Clone the repository to `/var/www/azana-erp`; `chown` it to the web user; make `storage` and `bootstrap/cache` writable.
3. Copy `deploy/env.production.example` to `.env`; fill every value marked CHANGE; `php artisan key:generate`; **store the key safely**.
4. nginx: install `deploy/nginx/azana.conf`, get certificates with certbot, reload.
5. Supervisor: install `deploy/supervisor/azana-worker.conf`; cron: install `deploy/crontab.example` for the web user.
6. `composer install --no-dev -o && npm ci && npm run build && php artisan migrate --force`.
7. Seed the safe, repeatable starting data (never demo users):
   `php artisan db:seed --class=RoleSeeder --force`, then `--class=MasterDataSeeder`, then `--class=FinanceSeeder`.
8. `php artisan erp:create-owner "Full Name" owner@azanafarms.com` (prompts for the password; set up two-factor on first sign-in).
9. `php artisan storage:link && php artisan erp:preflight`; fix any FAILED line. Then `php artisan erp:backup` and `php artisan erp:backup:test-restore`.
10. Load existing data (docs/DATA_IMPORT.md).

## Every later deployment

```
cd /var/www/azana-erp
HEALTH_URL=https://erp.azanafarms.com/health deploy/deploy.sh v1.4.0     # a tag, a branch or origin/main
```

The script backs up first, goes into maintenance mode, checks out the exact ref, installs, builds, migrates, refreshes roles/permissions (new modules arrive; edits are never overwritten), caches, runs the preflight, restarts the workers, goes live, and checks `/health`. If anything fails the site **stays in maintenance mode** and the script says so; nothing half-deployed is served.

After the first deployment of Phase 19, nothing else is needed. After later phases that add settings or modules, the script already does the needed seeding.

## Rolling back

1. The migration is the only irreversible part. If the new release has **not** changed the database shape in a way the old code cannot read (check the release's migration notes in `docs/IMPLEMENTATION_STATUS.md`):
   `git checkout --detach <previous-ref>` (the script printed it), then `composer install --no-dev -o`, `npm ci && npm run build`, `php artisan optimize:clear && php artisan config:cache route:cache view:cache`, `php artisan queue:restart`, `php artisan up`.
2. If the migrations must be undone: `php artisan migrate:rollback --step=N` (all migrations are reversible), then step 1.
3. If data was damaged: restore the backup the deploy script took before it started (docs/BACKUP_AND_RESTORE.md).

## Monitoring

`erp:monitor` (every 15 minutes) and the *Backups & monitoring* page cover: backup age, off-site copy, restore test, scheduler heartbeat, queue worker and failed jobs, free disk, database/cache/queue/storage, debug mode. `GET /health` returns 200/503 for an external uptime monitor (UptimeRobot or similar; alert on anything but 200). Logs rotate daily in `storage/logs` (`LOG_CHANNEL=daily`).

## Checklist for go-live day

- [ ] `erp:preflight` has no FAILED line
- [ ] Off-site backup configured; a backup and a restore test have passed
- [ ] Owner account has two-factor on; demo/test users do not exist
- [ ] Scheduler cron and queue worker running (the page shows both OK)
- [ ] Uptime monitor pointed at `/health`
- [ ] Historical data imported and reconciled with the old records
