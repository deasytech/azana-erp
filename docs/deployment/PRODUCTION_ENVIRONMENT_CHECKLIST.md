# Production environment checklist

The template is `deploy/env.production.example`; copy it to `/var/www/azana-erp/.env` on the server (`chmod 640`, owner `deploy`, group `www-data`). **Never commit the filled file, never paste it into a ticket or chat.** `php artisan erp:preflight` checks the rows marked ✔.

| Variable | Production value | Notes |
|---|---|---|
| `APP_NAME` | `"Azana Farms ERP"` | |
| `APP_ENV` | `production` ✔ | |
| `APP_DEBUG` | `false` ✔ | Debug on exposes code, paths and settings |
| `APP_KEY` | generated once with `key:generate` on the **first** install | Store in a password manager before first sign-in. **Never regenerate once data exists**: two-factor secrets cannot be read afterwards. If restoring onto a new server, reuse the saved key |
| `APP_URL` | `https://erp.azanafarms.com` ✔ (must be https) | Used in e-mail and notification links, which point to the ERP |
| `WEBSITE_HOST` | `www.azanafarms.com` ✔ | Public site answers only here |
| `ERP_HOST` | `erp.azanafarms.com` ✔ | Filament panel answers only here |
| API host | (no variable) | `api.azanafarms.com` is a name nginx maps to the same app and limits to `/api/v1`, `/docs`, `/health`, `/up`. API base URL for the mobile app: `https://api.azanafarms.com/api/v1` |
| `LOG_CHANNEL` / `LOG_LEVEL` / `LOG_DAILY_DAYS` | `daily` / `warning` / `30` | Never log passwords, tokens or secrets (the code already avoids it) |
| `DB_CONNECTION` | `mysql` ✔ | |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | MySQL listens on localhost only |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `azana_erp` / `azana` / long random | Dedicated account, never root |
| `SESSION_DRIVER` | `database` | |
| `SESSION_SECURE_COOKIE` | `true` | Cookies travel only over https |
| `SESSION_LIFETIME` | `120` | |
| `SESSION_DOMAIN` | empty | Each name keeps its own cookie |
| `SANCTUM_STATEFUL_DOMAINS` | empty | Bearer tokens only |
| `SANCTUM_EXPIRATION` | `43200` | Minutes; 30 days per device. Documented in `docs/API.md` |
| `QUEUE_CONNECTION` | `database` ✔ (not `sync`) | |
| `DB_QUEUE_RETRY_AFTER` | `3700` ✔ | Must exceed the longest job (3600 s) |
| `CACHE_STORE` | `database` ✔ | Shared lock store; not `array` or `file` |
| `FILESYSTEM_DISK` | `local` | Uploads stay on this server and are backed up by file copy (BACKUP guide §9) |
| `MAIL_MAILER` | `smtp` ✔ | Not `log` |
| `MAIL_HOST` / `MAIL_PORT` | provider's values; 587 (STARTTLS) or 465 (implicit TLS). Leave `MAIL_SCHEME` unset: Laravel chooses `smtps` for 465 and `smtp` otherwise (verified in `MailManager`) | **Needs from the mail host** |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | the `erp@azanafarms.com` mailbox | **Needs from the mail host** |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | `erp@azanafarms.com` / app name | Must be a mailbox on the same domain so SPF/DKIM already match |
| `BACKUP_OFFSITE_DISK` | `s3` ✔ | **Decision C1**; needs `league/flysystem-aws-s3-v3` |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` / `AWS_ENDPOINT` | the bucket's own | Private bucket, versioning and server-side encryption. Key restricted to that bucket. `AWS_ENDPOINT` only for non-AWS S3-compatible stores |
| `BACKUP_KEEP_DAYS` / `_WEEKS` / `_MONTHS` | `14` / `8` / `12` | |
| `BACKUP_RESTORE_TEST_DATABASE` | `azana_restore_test` | The DB user needs rights on it; it must not be the live database |
| `BACKUP_MYSQLDUMP_ARGS` | unset for MySQL 8; empty for MariaDB | |
| `MESSAGING_DRIVER` | unset | SMS/WhatsApp are off; see `config/messaging.php` if enabled later |
| `APP_MAINTENANCE_DRIVER` | `file` (default) | |

Not used by this application and therefore not to be set: Redis, Pusher/Reverb, Horizon, Octane, AWS SES/SQS.

## Files that must not be public

`.env`, `storage/logs`, `storage/app/private`, `storage/app/backups`, `vendor`, `database`. All are outside `public/`. Test after install (VERIFICATION_CHECKLIST §9): `curl -I https://erp.azanafarms.com/.env`, `/storage/logs/laravel.log`, `/composer.json` must not return 200.
