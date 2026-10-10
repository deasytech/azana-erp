# Production readiness audit

Date: 2026-10-09. Branch `phase-20-hardening-launch`. Scope: preparation only. **The application has NOT been deployed.**

## 1. What the application actually is

| Item | Finding | Source |
|---|---|---|
| Framework | Laravel 13.33 (`laravel/framework ^13.17`), Livewire 4.4.7 (through Filament 5.9), Sanctum 4.3 | `composer.json`, `php artisan --version` |
| PHP | `^8.3` (8.3.30 locally). Extensions: bcmath (declared), plus intl, mbstring, zip, gd, pdo_mysql, openssl, xml, fileinfo, ctype, session | `composer check-platform-reqs` passes; `RunPreflightChecks` lists the runtime set |
| Database | MySQL 8 (tested here on 8.4). Tests use SQLite in memory; the preflight warns if production is not MySQL | `.env`, `docs/DEPLOYMENT.md` |
| Frontend build | Vite 8 + Tailwind 4, three entry points (`app.css`, `app.js`, Filament `theme.css`) to `public/build`. **Vite 8 needs Node `^20.19 or >=22.12`.** `public/build` is git-ignored, so it must be built on deploy | `package.json`, `vite.config.js`, `.gitignore` |
| Filament assets | `public/js/filament`, `public/css/filament` are git-ignored and are published by the `post-autoload-dump` script (`filament:upgrade`) on `composer install` | `composer.json` |
| Web hosts | `WEBSITE_HOST` limits the public site, `ERP_HOST` limits the Filament panel. **API routes (`/api/v1`) and `/docs` have no host restriction** | `routes/web.php`, `routes/api.php`, `AdminPanelProvider` |
| Long-running processes | One queue worker and one cron line. No WebSockets, no Horizon, no Redis | see below |
| Scheduler | 10 entries: heartbeat (1 min), monitor (15 min), hourly postings and alerts, daily tasks 05:00, semen expiry daily, backup 02:00, reconcile 03:00, restore test Sunday 04:00 | `routes/console.php` |
| Queue | `database` driver. Only two job classes (`RunBackup`, `RunBackupRestoreTest`) plus queued notifications. Locks use the database cache (`onOneServer`, `withoutOverlapping`) | `app/Jobs`, `config/queue.php` |
| Mobile mutations | Idempotent by client UUID, processed in the request, not on the queue, so a queue retry cannot duplicate an inventory, finance or sync mutation | `docs/OFFLINE_SYNC.md`, `ProcessMutation` |
| Health | `/up` (framework) and `/health` (200/503, outside the session group) | `bootstrap/app.php` |
| Logs | `daily` channel, 14 days by default (`LOG_DAILY_DAYS`). Reported errors are tallied without messages | `config/logging.php`, `ErrorTally` |
| Uploads | Animal photos on the default disk, private. **Task evidence photos on the `public` disk** (`storage/app/public/task-evidence`, reachable at `/storage/...`). Import files on `local` | `PhotosRelationManager`, `ViewTask`, `CheckImport` |
| Mail | `MAIL_MAILER` env; about a dozen mail call sites (alerts, task assignment, enquiries) | `config/mail.php` |
| External services | None required. S3-compatible storage for off-site backups (see C1) | `config/backup.php` |
| Hard-coded development values | None in `app`, `config`, `resources/views`, `routes`, `bootstrap`. The only match is Sanctum's default stateful list (`localhost...`), irrelevant to bearer-token use and emptied in the production template | grep |

## 2. Findings

### Critical (a safe deployment cannot start until resolved)

**C1. Off-site backup is undecided and its package is not installed.** `BACKUP_OFFSITE_DISK` must name a disk that is not the VPS, and the preflight reports FAILED without it. `deploy.sh` stops at the preflight, so the very first deployment would stay in maintenance mode. The S3 driver needs `league/flysystem-aws-s3-v3`, which is **not** in `composer.json` or the lock file. It is the same package for AWS S3, Backblaze B2, Wasabi and Cloudflare R2 (set `AWS_ENDPOINT`); an SFTP target to another server would need `league/flysystem-sftp-v3` instead. *Not added*: it depends on the provider you choose and changes the lock file. **Decision needed: which off-site store.** Hostinger snapshots and backups are on the same provider and are not a substitute for an independent copy.

**C2. Moving the bare domain can break the company's e-mail.** The e-mail runs on Syskay cPanel under the same domain. If the MX records, `mail.`/`webmail.`/`cpanel.`/`autodiscover.` names or the SPF record depend on the bare domain's current address, changing that address silently redirects or de-authorises mail. This cannot be judged without the live zone file. The cutover is therefore split so that nothing touching e-mail is changed until the zone has been read and the three traps in `DNS_AND_EMAIL_CUTOVER.md` have each been ruled out.

### High (resolve before launch)

| # | Finding | Handled by |
|---|---|---|
| H1 | The old nginx file (also `http2 on;`, which Ubuntu 24.04's nginx 1.24 rejects) served only `www` and `erp`; the bare domain redirected to https but had no https server, and no `api` host existed. `/api/*` and the public `/docs` page answered on every host. | New `deploy/nginx/azana.conf`: apex redirects to `www`; `www` and `erp` return 404 for `/api/` and `/docs`; `api` serves only `/api/v1`, `/docs`, `/health`, `/up`. No code or API-contract change. |
| H2 | Node on Ubuntu's own packages is too old for Vite 8. `deploy.sh` runs `npm ci && npm run build` on the server. | Guide, section 3: install Node 22 LTS from NodeSource. |
| H3 | Mail delivery route is undecided. A VPS cannot reliably send mail directly (port 25 is normally blocked, and the sender would need SPF/DKIM changes). | Plan: send through the existing mailbox's SMTP (no DNS e-mail change). **Needs the SMTP host and an `erp@azanafarms.com` mailbox**; Syskay must allow SMTP logins from the VPS address. |
| H4 | The HTTPS certificate cannot be requested for names whose DNS still points to Syskay, and the old config could not start before a certificate existed. | `azana-bootstrap.conf` (port 80 only) then `azana.conf`; two certificates so `erp` and `api` can be proven before the website moves. |
| H5 | PHP's default upload limit (2 MB) is below nginx's 12 MB and the 10 MB import limit. | `deploy/php/99-azana.ini`. |
| H6 | A fresh `APP_KEY` is correct for a new, empty production database; it must never be regenerated afterwards (two-factor secrets are encrypted with it). | Checklist; key stored in a password manager before first sign-in. |

### Medium

| # | Finding | Note |
|---|---|---|
| M1 | Task evidence photos are on the public disk. File names are random hashes, but anyone with a URL can open one without signing in. | Acceptable at launch; if photos may show people or documents, move the disk to private with a signed route (a code change, not done). |
| M2 | The API reference at `/docs` needs no sign-in by design. | Now only on `api.azanafarms.com`. |
| M3 | Database queue and database cache are enough for this load (two job types; sync is in-request). Limits: no priority queues or batching, and the queue shares the database with the application. | Redis is **not** needed. Revisit only if `jobs`/`cache` table contention shows in the slow-query log. |
| M4 | Rate limiters key on the client IP. Correct while nginx talks to PHP-FPM directly; behind a CDN or load balancer every user would share one address until trusted proxies are configured. | Do not put Cloudflare proxying in front without adding `trustProxies`. |
| M5 | `deploy.sh` has never been run on a server. Rewritten on 2026-10-09 so a failed deployment restores the previous release instead of staying in maintenance mode, and tested in a local throwaway clone (success path, failure-after-migration path, unknown ref). See BLOCKER_RESOLUTION_PLAN.md §5. | A supervised rehearsal on staging, including a deliberately failing release. |
| M6 | `erp:backup` relies on `mysqldump` and `mysql` client tools being installed; the restore test needs rights to create `azana_restore_test`. | Guide, section 4. |
| M7 | The full test suite in one process did not finish in 12 minutes (Phase 20 note). Each folder passes alone. | See Verification checklist. The three tests believed to be date-dependent were not: lazy chart widgets (test-only fix, BLOCKER_RESOLUTION_PLAN.md §6). |

### Low

* The Vite config declares a Bunny-hosted font (`Instrument Sans`). Open the public site once with the browser network panel and confirm which third-party requests it makes, if any; this was not checked.
* Add an uptime monitor on `https://erp.azanafarms.com/health` and `https://api.azanafarms.com/health`.

### Not applicable / confirmed absent

No Redis, WebSocket server, Horizon, Octane, Node runtime service, microservices, or separate Laravel apps are required. The demo-data seeder refuses to run in production and the *Go-live data reset* page is disabled unless the `system.data_mode` setting is `demo` (default `live`); production is never seeded with demo users (`DatabaseSeeder` creates demo accounts only in `local`/`testing`).

## 3. Checks that were actually run (2026-10-09, developer machine, PHP 8.3.30 / MySQL 8.4 / Node 22.19)

| Check | Result |
|---|---|
| `composer validate` | valid |
| `composer install --no-dev --dry-run` | resolves; removes only dev packages |
| `composer audit` | no advisories |
| `composer check-platform-reqs` | all pass |
| `npm run build` | succeeds |
| `npm audit` | **could not run** (the registry mirror in use does not implement the audit endpoint) |
| `config:cache`, `route:cache`, `view:cache`, `event:cache`, `filament:optimize` | all succeed (caches cleared afterwards) |
| `php artisan erp:preflight` on the developer environment | correctly reports FAILED for local settings (APP_ENV, APP_DEBUG, http URL, retry_after, no off-site disk); extensions, folders, storage link, database OK. Proves the checks run, not that production passes |
| Pest, folders run separately: Hardening 50, Backup 23, Finance 37, Import 27, Ui 22, Farm 23, System 9, Mobile 42, Website 43, Identity 25, Tasks 27, Sales 52 | all passed |
| Pest Inventory 58 and Procurement 37 | originally failed on three lazy-chart assertions (not date-dependent); fixed in the tests only and re-run: Inventory, Procurement and Reporting all pass |
| `/admin/**` pages and every resource's view and edit page, on MySQL with three months of demo data | no server error (see `docs/IMPLEMENTATION_STATUS.md`) |

Not run, because they need a server or an account that does not exist yet: nginx syntax (`nginx` is not installed on the author's machine), supervisor, the cron scheduler, a real SMTP send, an S3 write, certbot, a backup restore on another machine, the mobile app against the API host, TLS, load. They are listed as "not run" in the verification checklist.

## 4. Changes made in this task

Files added or changed (no application code changed):

* `deploy/nginx/azana-bootstrap.conf` (new), `deploy/nginx/azana.conf` (rewritten), `deploy/nginx/snippets/azana-app.conf`, `azana-php.conf` (new)
* `deploy/php/99-azana.ini` (new)
* `deploy/env.production.example` (host, session, Sanctum, log and mail guidance)
* `docs/deployment/*` (this pack)
