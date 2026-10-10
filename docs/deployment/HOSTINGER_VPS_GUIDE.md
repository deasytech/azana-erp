# Hostinger VPS: architecture and build guide

**Nothing in this guide has been executed.** It is a plan to be followed on a new, empty VPS after the open decisions in the audit (C1, C2, H3) are answered. If the VPS already has software on it, stop and compare with section 2 before installing anything.

## 1. Target architecture

```
                         internet
                            |
        DNS:  azanafarms.com / www / erp / api  ->  <VPS_IPV4>
                            |
                  ufw: 22 (key only), 80, 443
                            |
        nginx  ------------------------------  certbot (Let's Encrypt, 2 certificates)
          |  one vhost per name, one document root:  /var/www/azana-erp/public
          |
     php8.3-fpm  --  ONE Laravel application (modular monolith)  --  MySQL 8 (localhost only)
          |                                      |
     cron: schedule:run (every minute)     supervisor: queue:work x2 (database queue)
```

| Name | Serves | Notes |
|---|---|---|
| `azanafarms.com` | 301 to `https://www.azanafarms.com` | Single canonical address for the website |
| `www.azanafarms.com` | Public website | `WEBSITE_HOST` |
| `erp.azanafarms.com` | Filament ERP | `ERP_HOST`, `APP_URL` |
| `api.azanafarms.com` | `/api/v1`, `/docs`, `/health`, `/up` only | Mobile base URL `https://api.azanafarms.com/api/v1`; documented contract unchanged |

One codebase and one database, as `docs/ARCHITECTURE.md` requires. No Redis, no second app, no extra database.

| Component | Choice | Why |
|---|---|---|
| OS | Ubuntu **24.04 LTS**, plain (no control-panel template) | Ships PHP 8.3 and MySQL 8.0, which are what the project is built and tested on. A panel template (cPanel, CloudPanel, Plesk) brings its own web server and PHP and would fight `deploy/`. |
| Web server | nginx | Configs in `deploy/nginx/` |
| PHP | 8.3 FPM + CLI, extensions `bcmath mbstring intl zip gd mysql xml curl` (fileinfo, openssl, session, tokenizer are built in) | `composer check-platform-reqs`, `RunPreflightChecks` |
| Database | MySQL 8.0 (Ubuntu package), bound to `127.0.0.1` | The documented production engine |
| Composer | 2.x, `composer install --no-dev -o` against the committed lock | `deploy.sh` |
| Node | **22 LTS** from NodeSource (Vite 8 needs 20.19+ / 22.12+; Ubuntu's own `nodejs` is too old) | `npm ci && npm run build` |
| Queue | `database` driver, two Supervisor workers | Only backups, restore tests and notifications are queued; mobile sync runs in-request and is idempotent |
| Cache / sessions | `database` | Locks that stop double postings need a shared store; the database serves it |
| Scheduler | cron, one line, as `www-data` | `deploy/crontab.example` |
| Mail | Authenticated SMTP to the existing company mailbox | No DNS e-mail change (see DNS_AND_EMAIL_CUTOVER.md) |
| Backups | `erp:backup` nightly to the `backups` disk **and** an off-site S3-compatible bucket | `docs/BACKUP_AND_RESTORE.md` |
| Sizing | 2 vCPU / 4 GB RAM / 40 GB disk is enough to start; watch disk (backups and photos) | Not measured: confirm the plan against real load after launch |

## 2. Before touching the server

Record: VPS plan, OS shown in hPanel, IPv4 (and IPv6 if given), the root/SSH access method. SSH in and run `cat /etc/os-release; dpkg -l | grep -E 'nginx|apache|php|mysql|mariadb|node' ; ss -tlnp`. If a web server or database is already running, decide with the owner whether to reuse or reinstall; **do not reinstall or reset an already configured VPS without explicit approval.**

## 3. Base server (once)

```bash
# as root, then create a normal sudo user
adduser deploy && usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/ && chown -R deploy:deploy /home/deploy/.ssh
# log in as deploy in a second window and confirm it works BEFORE the next step
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/; s/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
systemctl reload ssh

apt update && apt -y upgrade
apt -y install ufw unattended-upgrades fail2ban git unzip curl gzip supervisor nginx certbot \
  mysql-server mysql-client \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
ufw default deny incoming && ufw default allow outgoing && ufw allow OpenSSH && ufw allow 80,443/tcp && ufw enable
dpkg-reconfigure -plow unattended-upgrades
# Composer 2 (verify the installer signature as shown on getcomposer.org/download), then Node 22 from NodeSource (nodesource.com/products/distributions)
php -v; composer -V; node -v     # PHP 8.3.x, Composer 2.x, Node v22.x
```

`fail2ban` is included only because it is a one-line guard for SSH; remove it if you prefer the firewall alone. Do not open port 3306 or 25.

PHP settings: copy `deploy/php/99-azana.ini` to `/etc/php/8.3/fpm/conf.d/` and `/etc/php/8.3/cli/conf.d/`, `systemctl restart php8.3-fpm`.

## 4. MySQL

```sql
CREATE DATABASE azana_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'azana'@'localhost' IDENTIFIED BY '<GENERATE_A_LONG_RANDOM_PASSWORD>';
GRANT ALL ON azana_erp.* TO 'azana'@'localhost';
-- the weekly restore test loads the newest backup into a scratch database and drops it:
GRANT ALL ON azana_restore_test.* TO 'azana'@'localhost';
```

Run `mysql_secure_installation`. Keep the password only in the server's `.env` and a password manager. Check `bind-address = 127.0.0.1` in `/etc/mysql/mysql.conf.d/mysqld.cnf`.

## 5. Application

```bash
sudo mkdir -p /var/www/azana-erp /var/www/certbot && sudo chown deploy:www-data /var/www/azana-erp
git clone <REPOSITORY_URL> /var/www/azana-erp && cd /var/www/azana-erp
git checkout --detach <RELEASE_TAG_OR_COMMIT>           # a tag, never "whatever is on the branch"
cp deploy/env.production.example .env && chmod 640 .env && chown deploy:www-data .env
# fill every CHANGE value (PRODUCTION_ENVIRONMENT_CHECKLIST.md), then ONCE, on this first install only:
php artisan key:generate           # store the new APP_KEY in a password manager NOW
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund --ignore-scripts && npm run build
# storage and bootstrap/cache: group www-data, group-writable, new files inherit the group, never world-writable
echo 'umask 002' >> ~/.profile && umask 002      # files made by the deploy user (caches, logs) stay writable by PHP-FPM
sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R g+rwX storage bootstrap/cache && sudo find storage bootstrap/cache -type d -exec chmod g+s {} +
php artisan migrate --force        # ONLY on the new, empty database
php artisan db:seed --class='Database\Seeders\RoleSeeder' --force
php artisan db:seed --class='Database\Seeders\MasterDataSeeder' --force
php artisan db:seed --class='Database\Seeders\FinanceSeeder' --force
php artisan erp:create-owner "<FULL NAME>" owner@azanafarms.com     # prompts for a password
php artisan storage:link
```

Never run `db:seed` without `--class`, `DemoDataSeeder`, `migrate:fresh` or `migrate:refresh` on this server. (`DatabaseSeeder` creates demo accounts only in `local`/`testing`, and the demo seeder refuses to run in production, but do not rely on that.)

`deploy.sh` runs the same steps for every later release. The application files belong to `deploy`; only `storage` and `bootstrap/cache` are group-writable by `www-data`.

## 6. Background processes

```bash
sudo cp deploy/supervisor/azana-worker.conf /etc/supervisor/conf.d/ && sudo supervisorctl reread && sudo supervisorctl update
sudo crontab -u www-data -l 2>/dev/null; sudo cp deploy/crontab.example /tmp/c && sudo crontab -u www-data /tmp/c     # check the existing crontab first; this replaces it
```

* Workers: `--tries=3 --timeout=3600 --max-time=3600`, `DB_QUEUE_RETRY_AFTER=3700` (above the longest job, or a running backup is handed to a second worker). `queue:restart` in `deploy.sh` makes them reload new code.
* Failed jobs: `php artisan queue:failed`, inspect, then `queue:retry <id>` or `queue:forget <id>`. The *Backups & monitoring* page also alarms on failed jobs.
* Duplicates: sync mutations are idempotent by client UUID and are not queued; postings and backups take cache locks (`withoutOverlapping`, `onOneServer`). Do not run a second scheduler or change `CACHE_STORE` to `array`/`file` per server.
* Worker log (`storage/logs/worker.log`) is rotated by Supervisor (50 MB x 10); Laravel logs rotate daily, `LOG_DAILY_DAYS` days.

## 7. nginx and HTTPS (two certificates)

Prerequisite: the DNS names in use point to `<VPS_IPV4>` (DNS_AND_EMAIL_CUTOVER.md, phase 1 for `erp` and `api`; phase 2 for the bare domain and `www`).

```bash
sudo cp deploy/nginx/snippets/*.conf /etc/nginx/snippets/
sudo cp deploy/nginx/azana-bootstrap.conf /etc/nginx/sites-available/azana && sudo ln -sf /etc/nginx/sites-available/azana /etc/nginx/sites-enabled/azana
sudo rm -f /etc/nginx/sites-enabled/default && sudo nginx -t && sudo systemctl reload nginx

# Phase 1: erp + api (new names; safe, the old website is untouched)
sudo certbot certonly --webroot -w /var/www/certbot -d erp.azanafarms.com -d api.azanafarms.com
# Phase 2, after www and the bare domain point here:
sudo certbot certonly --webroot -w /var/www/certbot -d azanafarms.com -d www.azanafarms.com

sudo cp deploy/nginx/azana.conf /etc/nginx/sites-available/azana && sudo nginx -t && sudo systemctl reload nginx
sudo certbot renew --dry-run          # certbot installs its own renewal timer
```

`azana.conf` refers to both certificates. If only phase 1 is done, temporarily remove the two `azanafarms.com`/`www` server blocks (or `nginx -t` will refuse); restore them in phase 2. Reload nginx after each certificate renewal (`/etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh` containing `systemctl reload nginx`).

Nothing but `/var/www/azana-erp/public` is the document root; `.env`, `vendor`, `storage/logs` and backups are above it, and dotfiles are denied.

## 8. First start

```bash
php artisan erp:preflight        # every line OK; fix any FAILED
php artisan erp:backup && php artisan erp:backup:test-restore
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache && php artisan filament:optimize
```

Sign in at `https://erp.azanafarms.com/admin` as the owner, turn on two-factor, and run `VERIFICATION_CHECKLIST.md`. Then follow `docs/LAUNCH_CHECKLIST.md` for data load and go-live.

## 9. Backups and monitoring on this server

* Nightly `erp:backup` at 02:00 (database, gzip, checksum, off-site copy); weekly restore test; retention 14 days / 8 weeks / 12 months. Details and the restore procedure: `docs/BACKUP_AND_RESTORE.md`.
* Files: `storage/app/private` and `storage/app/public` (photos, task evidence, import uploads) are **not** in the database backup. Copy them off the server nightly, e.g. `rsync`/`rclone` to the same bucket under another prefix, or take a VPS snapshot as an extra, not the only, copy.
* Keep `.env` and `APP_KEY` in a password manager, not only on the server.
* Disk space is checked by `erp:monitor`; add an external uptime check on both `/health` URLs.

## 10. Releasing and rolling back

Routine release: `HEALTH_URL=https://erp.azanafarms.com/health deploy/deploy.sh <TAG>` (backup first, maintenance mode, migrate, cache, preflight, restart workers, health check; on any failure the site stays down on purpose).

Rollback, in order of preference:

1. **Code only** (no migration in the release, or the old code reads the new schema): `git checkout --detach <PREVIOUS_REF>`, `composer install --no-dev -o`, `npm ci && npm run build`, `php artisan optimize:clear` then the four `*:cache` commands, `php artisan queue:restart`, `php artisan up`.
2. **Migration must be undone**: `php artisan migrate:rollback --step=<N>` (all migrations are reversible) before step 1. Take a manual `erp:backup` first.
3. **Data damaged**: restore the backup the deploy script took before it began, following `docs/BACKUP_AND_RESTORE.md`. Anything entered after that backup is re-entered; phones re-sync their queue.
4. **Server lost**: rebuild from sections 3-8, restore the newest off-site backup and the file copy, using the saved `.env`/`APP_KEY`.

DNS rollback (website only) is in `DNS_AND_EMAIL_CUTOVER.md`.

## 11. What must wait for approval

Any change to a server, DNS record or the e-mail host; installing software; the first `migrate --force`; creating the owner account; requesting certificates; adding the S3 package (C1); enabling the cron/queue; the DNS flip of the bare domain and `www`.
