# Verification checklist (acceptance test plan)

Rule: a row is marked **Done** only when it was actually executed and its result inspected. Rows below that were executed on the developer machine on 2026-10-09 say so; everything that needs the VPS is **Not run**. Copy this file when running it on the server and fill in date, tester and result.

| # | Area | Test | Where | Status |
|---|---|---|---|---|
| 1 | Dependencies | `composer install --no-dev --prefer-dist -o` using the committed lock completes; `composer audit` clean | server | dry-run and audit **Done** (dev machine); real install **Not run** |
| 2 | Frontend | `npm ci && npm run build` produces `public/build`; Filament assets present in `public/js/filament` | server | build **Done** (dev machine); on server **Not run** |
| 3 | Caching | `config:cache route:cache view:cache event:cache filament:optimize` succeed | server | **Done** (dev machine); server **Not run** |
| 4 | Preflight | `php artisan erp:preflight` has no FAILED line with the production `.env` | server | Command runs and rejects local settings (**Done**); production pass **Not run** |
| 5 | Database | `php artisan migrate:status` all "Ran"; `migrate --force` on the empty production database; `db:seed` of the three safe seeders; `erp:create-owner` | server | **Not run** |
| 6 | Auth / Filament | Owner signs in at `https://erp.azanafarms.com/admin`, sets up two-factor; a role without permission sees nothing it should not; `/admin` on `www` and `api` is 404 | server | **Not run** |
| 7 | Public site | `/`, `/about`, `/operations`, `/sustainability`, `/products`, `/contact`, `/sitemap.xml`, `/robots.txt`; contact form creates an enquiry; bare domain redirects to `www`; `/api/v1/me` and `/docs` are 404 on `www` and `erp` | server | website suite **Done** (43 tests); live **Not run** |
| 8 | Uploads | Upload an animal photo and a task photo; photo opens only when signed in; the import file (10 MB) is accepted; 13 MB is refused | server | **Not run** |
| 9 | Exposure | `curl -I` for `/.env`, `/composer.json`, `/storage/logs/laravel.log`, `/vendor/autoload.php`, `/.git/config` on each host: none returns 200 | server | **Not run** |
| 10 | Debug | Request a missing page and force an error: no stack trace, paths or settings shown | server | **Not run** |
| 11 | HTTPS | All four names serve a valid certificate; http redirects; HSTS header present; cookies carry `Secure`; `certbot renew --dry-run` | server | **Not run** |
| 12 | Mail | Test message from the ERP arrives; SPF and DKIM `pass` in the headers; a task assignment notice arrives | server | **Not run** |
| 13 | Scheduler | `crontab -u www-data -l` shows the line; *Backups & monitoring* shows the scheduler heartbeat OK within 2 minutes | server | **Not run** |
| 14 | Queue | `supervisorctl status` shows 2 workers; press *Back up now* and see the result; `kill` a worker and see Supervisor restart it; `queue:restart` after a deploy | server | **Not run** |
| 15 | Failed jobs | Cause a failed job on a test copy, see it in `queue:failed` and the monitor, retry it | staging | **Not run** |
| 16 | API auth | `POST api.azanafarms.com/api/v1/auth/login` with a Farm Worker returns a token; `GET /me` with it works; without it 401; a role requiring two-factor gets 403 `two_factor_required` | server | mobile suite **Done** (42 tests); live **Not run** |
| 17 | API limits | 21st login attempt in a minute from one address returns 429; 6th for one email returns 429 | server | covered by tests **Done**; live **Not run** |
| 18 | API contract | `/docs` on `api` lists the same endpoints as `docs/API.md`; the contract tests pass | server + dev | tests **Done** (mobile suite); live **Not run** |
| 19 | Offline sync | Push the same `client_id` twice: one record, the second answer reports the first result without repeating it; push an old stock change: conflict is held for review; airplane-mode test on a phone | server + phone | idempotency and conflict tests **Done** (dev machine); phone **Not run** |
| 20 | Inventory / finance integrity | `php artisan erp:reconcile` all OK after a real stock receipt, sale and payment; trial balance balanced | server | covered by Hardening tests (50) **Done**; live **Not run** |
| 21 | Audit | The receipt/sale/payment above each appear in the audit trail with user, time, device | server | **Not run** |
| 22 | Backups | `erp:backup` succeeds, file appears off-site, checksum matches; `erp:backup:test-restore` passes | server | tests **Done** (Backup suite, 23); live **Not run** |
| 23 | Restore drill | Restore the off-site file on a spare machine (BACKUP_AND_RESTORE.md steps 2-6), sign in, count rows; record the time it took | spare machine | **Not run** |
| 24 | Rollback | A deliberately failing release makes `deploy.sh` restore the previous release and leave the site up; `/health` is 200 | staging | Failure-after-migration path **Done** in a local throwaway clone (SQLite); on the VPS **Not run** |
| 25 | DNS / e-mail | Section 5 of DNS_AND_EMAIL_CUTOVER.md after the move: inbound and outbound company e-mail, webmail, phone settings | live | **Not run** |
| 26 | Monitoring | Uptime check on both `/health` URLs alerts when nginx is stopped on a test window | live | **Not run** |
| 27 | Security | `ufw status` shows only 22/80/443; SSH password login refused; MySQL not reachable from outside; `fail2ban-client status sshd` | server | **Not run** |
| 28 | Full test suite | `vendor/bin/pest` per folder on the release commit | dev / CI | Per folder **Done** on 2026-10-09: Hardening, Backup, Finance, Import, Ui, Farm, System, Mobile, Website, Identity, Tasks, Sales, Inventory, Procurement and Reporting all pass (the chart-test failures were lazy-widget assertions, now fixed in the tests). A single all-in-one run **did not finish** in 12 minutes on the author's machine; not retried |

## Go / no-go

Go only when rows 1-14, 16, 19-22 and 24 are Done on the server, row 23 is Done at least once, rows 25 and 11 are Done after DNS, and `docs/LAUNCH_CHECKLIST.md` is signed. **Until then the application has not been deployed and is not production-ready.**
