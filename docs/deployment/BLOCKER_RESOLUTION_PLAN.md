# Deployment blocker resolution plan

Date: 2026-10-09. Follows `PRODUCTION_READINESS_AUDIT.md`. **The application has not been deployed. No server, DNS, certificate, package or production migration has been touched.**

Legend: **[V]** verified here by running or reading it; **[A]** assumption that must be confirmed; **[D]** a decision or information only you can give.

## 1. Off-site backups (blocker C1)

### What has to exist
The app copies each nightly backup to the disk named in `BACKUP_OFFSITE_DISK`, and the preflight FAILS (and `deploy.sh` refuses to continue) without it **[V]**. `config/filesystems.php` already defines an `s3` disk with `key`, `secret`, `region`, `bucket`, `endpoint`, `url`, `use_path_style_endpoint` **[V]**, so any S3-compatible store works with **no application code change**, only one Composer package: `league/flysystem-aws-s3-v3 ^3.0` (not yet in `composer.json`; **not installed**, awaiting your approval) **[V]**.

Size is small: a gzip dump of three months of busy demo data is **0.4 MB** (database 7.9 MB) **[V]**. With the default retention (14 daily, 8 weekly, 12 monthly, about 34 copies) the database backups need well under 1 GB for years **[A: real data grows faster than demo data, but a 100x margin is still about 1.4 GB]**. The photos and uploads are the bigger item and are copied separately (guide §9).

### Options (prices checked on the providers' own pages on 2026-10-09 unless marked)

| | **Backblaze B2** | **Cloudflare R2** | **Wasabi** |
|---|---|---|---|
| Storage | $6.95 per TB-month **[V]** | $0.015 per GB-month ($15/TB) **[V]** | $7.99 per TB-month from 1 July 2026 **[V]** |
| Free allowance | first 10 GB free **[V]** | 10 GB-month, 1M writes, 10M reads free **[V]** | none; **1 TB minimum charge** and 90-day minimum retention per object (secondary sources, **[A]**) |
| Egress (restoring) | free up to 3x stored data per month, then $0.01/GB **[V]** | free **[V]** | free while under stored volume **[A]** |
| Request fees | most calls free **[V]** | $4.50 per million writes, $0.36 per million reads **[V]** | none **[A]** |
| Immutability | Object Lock and encryption **[V]**; bucket versioning ("keep all versions") **[A]** | versioning/lock features differ; confirm in the dashboard **[A]** | Object Lock **[A]** |
| S3-compatible API | yes **[V]** | yes **[V]** | yes **[A]** |
| Likely monthly cost for this app | **$0** (inside free 10 GB) | **$0** (inside free 10 GB) | **about $8** (the minimum charge) |

AWS S3 is the reference but is not recommended here: it costs more per GB, its egress is not free, and you would have to price an African region **[A: not checked]**.

**Recommendation: Backblaze B2** for the database backups: cheapest at scale, free at this size, bucket versioning and Object Lock available, application keys can be limited to one bucket, and the S3 API is supported. **Cloudflare R2** is an equally good choice if the company already has a Cloudflare account (free egress). Wasabi is the least suitable: the 1 TB minimum and 90-day minimum retention make small, pruned backups cost more than the others and fight the app's own pruning.

### Minimum credentials and configuration (nothing is created or stored yet)
1. One **private** bucket dedicated to this app (for example `azana-erp-backups`), server-side encryption on, versioning ("keep all versions") on.
2. One **application key limited to that bucket** with list, read, write and delete rights (delete is needed for the retention rule). No account-wide master key on the server.
3. In `.env` on the server only:

| Variable | B2 | R2 |
|---|---|---|
| `BACKUP_OFFSITE_DISK` | `s3` | `s3` |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | the application key id / secret | the R2 token's access key / secret |
| `AWS_DEFAULT_REGION` | the bucket's region, e.g. `us-west-004` | `auto` |
| `AWS_BUCKET` | bucket name | bucket name |
| `AWS_ENDPOINT` | `https://s3.<region>.backblazeb2.com` | `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` (safe default for non-AWS stores) **[A]** | `true` **[A]** |

4. Upload photos/task evidence separately (same bucket, another prefix, or a second bucket) with `rclone` on a nightly cron **[A]**.

### Two cautions to decide knowingly
* **Object Lock vs the retention rule.** `PruneBackups` deletes expired backups. A bucket-wide lock longer than the shortest retention would make those deletions fail. Use versioning at first; add Object Lock only with a lock period shorter than the daily retention **[A: needs a trial on the chosen provider]**.
* **A stolen server key could delete backups.** Versioning keeps the older versions after an S3 delete on B2 (the delete hides the file) **[A: confirm in a trial]**; this is why the key must be bucket-limited and why an occasional manual copy elsewhere is worthwhile.
* **Where the data lives** (US/EU region) may matter under the Nigeria Data Protection Act for customer and staff data **[D: owner/legal to confirm]**.

### [D] Needed from you
Which provider; approval to add `league/flysystem-aws-s3-v3` (changes `composer.json` and `composer.lock`); the owner (not me) creates the account, bucket and key and puts the key only into the server's `.env`.

## 2. Syskay SMTP (without changing DNS)

Mail leaves through the mailbox the company already uses, so SPF and DKIM, which were set up for Syskay's server, stay valid and **no DNS record is touched** **[A: normal for cPanel hosting; confirm DKIM is enabled for the domain on their server]**. Syskay's own settings page was not found publicly; the standard cPanel values below are what to confirm with them **[A]**.

| Laravel `.env` field | Value | Source |
|---|---|---|
| `MAIL_MAILER` | `smtp` | **[V]** |
| `MAIL_HOST` | the outgoing server for the mailbox: usually `mail.azanafarms.com` or Syskay's server name, **whichever name its TLS certificate covers** | **[D]** from Syskay |
| `MAIL_PORT` | `465` (implicit TLS) or `587` (STARTTLS) | **[D]** |
| `MAIL_SCHEME` | leave **unset**: Laravel picks `smtps` for 465 and `smtp` otherwise **[V]** (`MailManager`) | |
| `MAIL_USERNAME` | the full mailbox address, e.g. `erp@azanafarms.com` | **[D]** |
| `MAIL_PASSWORD` | that mailbox's password (a dedicated mailbox, not a person's) | **[D]** |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | the same mailbox / "Azana Farms ERP" (a different From than the login is often refused) | **[D]** |
| `MAIL_EHLO_DOMAIN` | optional; defaults to the host of `APP_URL` (`erp.azanafarms.com`) **[V]** | |

Questions for Syskay (their answers decide whether this works): (1) may a server outside their network log in to SMTP with this mailbox, or must the VPS IP be allowed; (2) the exact outgoing host and ports; (3) the hourly sending limit per mailbox/domain (cPanel hosts commonly cap it; alerts and task notices are a few dozen a day **[A]**); (4) is DKIM signing on for outgoing mail from that mailbox.

If their certificate does not match the host name, prefer a host name that does. Disabling verification (`MAIL_URL=smtp://user:pass@host:587?verify_peer=0`) works **[V in Symfony Mailer]** but removes protection; use it only as a documented stop-gap.

Test (read-only, on the VPS, after approval): `openssl s_client -starttls smtp -connect HOST:587 -brief </dev/null` shows the certificate name; then one test message and a look at the `Received-SPF` and `DKIM-Signature` headers.

## 3. Hostinger VPS pre-deployment checklist (all **[A]** until run on the VPS; every command is read-only)

| # | Check | Command / expectation |
|---|---|---|
| 1 | OS | `lsb_release -d` -> Ubuntu **24.04 LTS**, plain image (no panel) |
| 2 | Nothing pre-installed that competes | `ss -tlnp`; `dpkg -l | grep -E 'apache|nginx|mysql|mariadb|php'` -> empty, or decide what to keep. Do not reinstall a configured VPS without approval |
| 3 | Resources | `nproc; free -h; df -h /` -> at least 2 vCPU, 4 GB, 40 GB free |
| 4 | PHP 8.3 compatible | `apt-cache policy php8.3-fpm` offers 8.3.x; after install `php -v`; `composer check-platform-reqs` all green (passes on 8.3.30 locally **[V]**; the server's patch version may be older, so this must be run) |
| 5 | Extensions | `php -m | grep -E 'bcmath|intl|mbstring|zip|gd|pdo_mysql|xml|curl'` |
| 6 | Node 22 | NodeSource 22.x: `node -v` >= v22.12 (Vite 8 needs `^20.19 || >=22.12` **[V]**) |
| 7 | nginx version | `apt-cache policy nginx`. Ubuntu 24.04 ships 1.24 **[A]**, which is why the config uses `listen ... http2` rather than `http2 on;` (see §4) |
| 8 | MySQL 8 | `mysql --version` 8.0.x; the app was exercised on 8.4 **[V]**, so migrations are only proven on 8.4 until run on the server **[A]**. Confirm `sql_mode` has no surprises: `SELECT @@sql_mode;` |
| 9 | Supervisor / cron | `supervisord -v`; `systemctl status cron` active |
| 10 | Firewall | `ufw status` -> deny incoming; allow 22, 80, 443 only; confirm 3306 closed from outside: `nmap -p 3306 <VPS_IPV4>` from another machine |
| 11 | SSH | key login for a non-root user works in a second window **before** disabling password and root login; `sshd -T | grep -E 'passwordauthentication|permitrootlogin'` |
| 12 | Time | `timedatectl` -> synchronised; timezone decided (the app stores UTC unless configured **[A]**) |
| 13 | Outbound | `curl -sI https://registry.npmjs.org`, `https://packagist.org`, `https://github.com`; outbound 465/587 reachable: `nc -zv <MAIL_HOST> 587` |
| 14 | DNS ready for staging | `dig +short erp.azanafarms.com api.azanafarms.com` returns `<VPS_IPV4>` (only after you add the two records) |
| 15 | TLS | after DNS: `certbot certonly --webroot ...` with `--dry-run` first (a request, so needs approval) |

## 4. Nginx review

Nginx is **not installed** here and Docker's daemon is not running, so **the file has not been syntax-checked** **[V: unchecked]**. It was reviewed by reading, against the directive rules.

Found and fixed:
* **`http2 on;` fails on Ubuntu 24.04's nginx 1.24** (the directive arrived in 1.25.1) **[A on the packaged version]**. Replaced by `listen 443 ssl http2;`, which works on 1.24 and only warns on newer versions.
* **Missing certificates stop nginx starting.** Handled by the two-step `azana-bootstrap.conf` then `azana.conf`, and by two separate certificates (§7 of the guide).

Remaining risks to check on the server (not defects found, things that only a live test shows):
* `add_header` inside `location ^~ /build/` replaces the server-level security headers for those static files (harmless, asset files only).
* The `api` host uses `rewrite ^ /index.php last;` plus an exact `location = /index.php`. This is the intended allow-list; confirm `/api/v1/me` reaches Laravel (401, not nginx 404) and `/admin`, `/livewire-*`, `/storage/x` give nginx 404.
* The first 443 server (the bare domain) is nginx's default for unknown names, so a request by IP gets a redirect to `www` after a certificate warning. Acceptable; a dedicated default server would need its own certificate.
* `client_max_body_size` 2 MB on `api`: the sync endpoint accepts at most 100 mutations per push (`MAX_BATCH = 100`) **[V]**, which is far below 2 MB of JSON unless payloads contain embedded photos (the API does not take photos **[V per docs/API.md]**).

Commands to run on the server before enabling anything (nothing reloads until they pass):

```bash
nginx -v                                   # >= 1.24
sudo nginx -t                              # syntax of the live configuration, including includes
sudo nginx -T 2>/dev/null | grep -nE 'server_name|listen|ssl_certificate |include' | head -60     # what is actually loaded
# test a candidate file without touching the live one:
sudo nginx -t -c /etc/nginx/nginx.conf     # after copying the candidate into sites-available and linking it
# after reload, with the cert in place:
curl -sI --resolve www.azanafarms.com:443:127.0.0.1 https://www.azanafarms.com/ | head -5
for h in erp www api; do for p in /api/v1/me /docs /health /admin/login; do printf "%s %s -> " $h $p; curl -sk -o /dev/null -w "%{http_code}\n" --resolve $h.azanafarms.com:443:127.0.0.1 https://$h.azanafarms.com$p; done; done
```
Expected: `api /api/v1/me` 401, `api /docs` 200, `api /health` 200, `api /admin/login` 404, `www|erp /api/v1/me` 404, `www|erp /docs` 404, `erp /admin/login` 200, `www /health` 200.

## 5. `deploy.sh` review and the changes made

Original behaviour **[V by reading]**: stayed in maintenance mode on any failure, by design; checked only that `erp:backup` exited 0; `npm`/`composer` ran while down; no lock; the ERR trap did not fire inside functions.

Rewritten (`deploy/deploy.sh`), **tested in a throwaway clone with its own SQLite database on this machine, not on a server [V]**:

| Concern | Now |
|---|---|
| Stuck in maintenance | After maintenance mode starts, any failure (error, Ctrl-C, SIGTERM) triggers recovery: undo the migrations this run applied, check out the previous commit, reinstall and rebuild it, bring the site up. Tested: a preflight failure after a migration undid it and left the site live on the old release, exit code 1 |
| Recovery itself fails | The site stays down on purpose and the script prints the exact manual steps; serving old code on a half-migrated database is worse. This is the only way it can stay down. **Not exercised** (no way to force an irreversible migration safely) |
| Backup verification | Not just exit code: a fresh (15 min), successful, non-empty, checksummed run, and copied off-site if an off-site disk is configured. Tested on the success and failure paths |
| Preflight | Still runs after migrating and still gates going live; **not bypassed** |
| Migration safety | Pending migrations are listed first; `migrate --force --isolated` (cache lock); the count applied is remembered so exactly those are rolled back |
| Failed before maintenance | Unknown ref, dirty tree, failed backup, concurrent deployment all stop with the site untouched. Unknown ref tested |
| Unhealthy after going live | Site stays up, script exits 1 and prints the previous commit to roll back to (it does not roll back automatically while users are on it) |

Limits found: (1) `deploy.sh` needs a database that is already migrated: on an empty database `erp:backup` fails because `backup_runs` does not exist yet **[V]**, so the first installation is manual (guide §5), as already documented. (2) A release whose own migration creates the backup tables would fail at the backup step; irrelevant for production because the tables exist from first installation. (3) Rollback of data-changing migrations restores the schema, not data; the pre-deploy backup is the real safety net.

## 6. The two "date-dependent" chart tests

Finding **[V]**: they were mislabelled. There are three, not two, and **none depends on the date**. Run at 2026-09-20, 10-09, 10-16 and 11-05 the result was identical. The cause: Filament chart widgets are *lazy* (Livewire renders a placeholder first and fills the chart in a second request), so the heading is not in the server-rendered page HTML, but these tests asserted it there with `get(page)->assertSee('<chart heading>')`. Setting the widget non-lazy made the test pass, confirming it. The application behaves correctly (lazy loading is intended).

Fix is **test-only, no behaviour changed**: the page assertion became `assertSeeLivewire(<Widget>::class)` (the page carries the widget), while the heading is still asserted on the widget itself with `Livewire::test(...)`. Files: `InventoryUiTest`, `ProcurementUiTest` (three assertions), `ReportingUiTest`. Inventory (58), Procurement (37) and Reporting suites now pass: 0 failures **[V]**. The earlier belief that these only fail on some dates is retired; why they passed when written is not known **[A: a Filament update changed the placeholder; not confirmed]**.

## 7. Mobile API hostname and contract **[V]**

With `WEBSITE_HOST=www.azanafarms.com` and `ERP_HOST=erp.azanafarms.com` set, requests carrying `Host: api.azanafarms.com` were run through the real application (against the dev database, with its token deleted afterwards):
* `POST /api/v1/auth/login` 200 with `token`, `expires_at`; `GET /api/v1/me` and `/tasks` 200 with the token; `/me` without a token or with a bad token **401**; `POST /auth/logout` 200.
* `/docs` 200 and shows base URL `https://api.azanafarms.com/api/v1`; `/health` and `/up` 200; `/` and `/admin` 404 on the api host; website 200 on `www`; ERP login 200 on `erp`.
* `sync/status` without `device_id` answers 422, as before (validation, not a host effect).
So the API routes and responses are identical on any host; the only change is where the phone points. The Mobile suite (42 tests) passes **[V]**. `docs/API.md` says "Base URL: `https://<host>/api/v1`", so no contract text changes; the new mobile base URL is `https://api.azanafarms.com/api/v1`. Not verified: the real mobile app against the real host over TLS.

## 8. Decisions and information required to begin the controlled staging deployment

"Staging" here = the real VPS, with only the new names `erp` and `api` (no change to the live website or e-mail), data empty and disposable until sign-off.

1. **VPS access [D]:** IPv4 (and IPv6), the OS image installed, and how I connect (a non-root sudo user with your public key). Confirm it is empty (checklist §3 row 2).
2. **Code access [D]:** the repository URL and a read-only deploy key or token, and the commit/tag to deploy (`phase-20-hardening-launch` at the commit you approve; a tag is better).
3. **Off-site store [D]:** B2 or R2 (recommended: B2), and **approval to add `league/flysystem-aws-s3-v3`**; you create the bucket and the bucket-limited key and place the key in the server's `.env` yourself.
4. **DNS [D]:** permission and access to add exactly two records, `A erp` and `A api` -> `<VPS_IPV4>` (no existing record is edited). Also send the full Syskay zone export so the bare-domain traps can be checked later (not needed for staging).
5. **Mail [D]:** the `erp@` mailbox, SMTP host/port/password from Syskay and their answers to the four questions in §2. If not yet available, staging can run with `MAIL_MAILER=log`; the preflight then shows a warning, not a failure.
6. **Approval to proceed [D]** for, in this order: server hardening and packages (§3); creating the empty database; certificate request for `erp` and `api`; first `migrate --force` on the empty staging database; creating the owner account.

Not needed for staging: the bare-domain/`www` change, any e-mail DNS record, the final data import.

After that, the order is: base server -> database -> code and `.env` -> first install by hand -> nginx bootstrap -> certificates -> nginx full -> Supervisor and cron -> `erp:preflight` -> `erp:backup` and restore test -> `VERIFICATION_CHECKLIST.md` rows 1-22 -> `deploy.sh` rehearsal with a no-op tag and with a deliberately failing one on staging.
