# Backup and restore

## What happens automatically

The scheduler (one cron line, see `deploy/crontab.example`) runs:

| When | Command | What |
| --- | --- | --- |
| Daily 02:00 | `erp:backup` | Dumps the database (MySQL: `mysqldump --single-transaction`, consistent while people work), compresses it, reads it back to prove the file is whole, records a SHA-256, stores it on the `backups` disk and copies it to the **off-site disk**. Then applies retention. |
| Sunday 04:00 | `erp:backup:test-restore` | Loads the newest backup into a scratch database, checks the people, roles and migrations came back, drops the scratch database. |
| Every 15 min | `erp:monitor` | Checks backups, off-site copy, restore test, scheduler, queue worker, disk space and the core services. A failure notifies everyone with the *Backups & monitoring* permission (bell, and e-mail when switched on), once a day for the same problem. |

Every attempt, success or failure, is a row in **Administration > Backups & monitoring**.

## Setting it up

1. `.env`: `BACKUP_OFFSITE_DISK` names a disk that is **not this server** (for S3: `composer require league/flysystem-aws-s3-v3 "^3.0"`, fill `AWS_*`, use a private bucket with versioning and server-side encryption). Without it the monitor reports a failure: a backup on the same machine does not survive losing the machine.
2. `BACKUP_RESTORE_TEST_DATABASE` (default `azana_restore_test`): the MySQL account must be allowed to create and drop it. It must not be the live database (the code refuses).
3. MariaDB: set `BACKUP_MYSQLDUMP_ARGS=` (empty). MySQL 8 keeps the default `--set-gtid-purged=OFF`, without which a dump cannot be loaded into another database.
4. Install the cron line and the queue worker (`deploy/supervisor/azana-worker.conf`). *Back up now* and *Test a restore* on the page run on the queue.
5. Run `php artisan erp:backup` and `php artisan erp:backup:test-restore` once by hand and see both succeed.

## Retention

Every backup of the last 14 days; then the newest of each week for 8 weeks; then the newest of each month for 12 months (`BACKUP_KEEP_DAYS/WEEKS/MONTHS`). The newest good backup and the backup behind the last passed restore test are never removed. History rows stay after the files go.

## What is not in the database backup

Uploaded files (animal photos, lab attachments) live in `storage/app/private` and `storage/app/public`. Either point `FILESYSTEM_DISK` at an off-site disk, or copy those folders off the server regularly (`rsync`, or a server snapshot). The `.env` file and `APP_KEY` are also not in the backup: keep them in a password manager. **Without the `APP_KEY`, encrypted values (two-factor secrets) cannot be read after a restore.**

## Restoring after a disaster (the procedure)

Do the drill below before you need it.

1. **Stop writes.** `php artisan down` on the damaged server, or build the replacement server first (PHP 8.3, MySQL, the code at the deployed git ref, `.env` with the saved `APP_KEY`).
2. **Get the file.** Newest backup from the off-site disk (or `storage/app/backups/database`). Check it: `sha256sum file.sql.gz` against the checksum on the Backups page, or `gzip -t file.sql.gz`.
3. **Create an empty database** and load it:
   ```
   mysql -e "CREATE DATABASE azana_erp CHARACTER SET utf8mb4"
   gzip -dc azana-20261009-020000.sql.gz | mysql azana_erp
   ```
4. **Bring the code up to date with the data.** `php artisan migrate --force` (applies migrations newer than the backup), `php artisan erp:preflight`.
5. **Restore uploaded files** into `storage/app`, run `php artisan storage:link`, `php artisan queue:restart`, `php artisan up`.
6. **Check.** Sign in; open *Backups & monitoring*; compare the latest transactions with what staff remember. Anything entered after the backup (up to 24 hours) must be re-entered; mobile devices re-sync their queued entries by themselves (docs/OFFLINE_SYNC.md).
7. Take a fresh backup at once: `php artisan erp:backup`.

## The restore drill (do it each quarter, and after changing servers)

1. Run *Test a restore* on the Backups page (or `erp:backup:test-restore`) and read the result: tables and rows restored, users present.
2. Once a quarter go one step further on a spare machine: follow steps 2-6 above with a real off-site file, and sign in. Record the date and how long it took.
3. If the test ever fails, the monitor alerts; treat it as an outage of your safety net and fix it the same day.

## Targets

Recovery point: up to 24 hours of data (nightly backup). To shorten it, schedule `erp:backup` more often (cheap: the dump is small) or enable MySQL binary logging for point-in-time recovery. Recovery time: the time to build a server plus the dump load (minutes for this database size).
