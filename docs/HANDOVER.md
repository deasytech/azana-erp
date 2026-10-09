# Handover

For the person who looks after the Azana Farms ERP day to day, and for the next developer.

## What the system is

One Laravel application (modular monolith) serving three faces from one code base and one database: the ERP for staff (Filament, `ERP_HOST`), the public website (`WEBSITE_HOST`) and the mobile API (`/api/v1`). Read in this order: `ERP_MASTER_PLAN.md` (what and why), `ARCHITECTURE.md` (layers and rules), `DATABASE_ARCHITECTURE.md`, `DOMAIN_RULES.md`, then `IMPLEMENTATION_STATUS.md` (what each phase built, known issues).

Business rules live in `app/Domain/<Module>/Actions`. Filament screens, the API, imports and jobs all call the same actions, so a rule is changed in one place. Stock and money are append-only ledgers: a mistake is reversed by a new line, never edited.

## The routine

| When | Who | What |
| --- | --- | --- |
| Every morning | System administrator | Open *Administration > Backups & monitoring*. Everything OK. A red row is explained on the row; the matching section below says what to do. |
| Every morning | Managers | *Approvals*, *Alerts*, *Tasks*. |
| Weekly | System administrator | Confirm Sunday's restore test passed. Look at failed jobs (`php artisan queue:failed`). |
| Monthly | Accountant | Post the month's documents, review the trial balance, close the period (*Farm settings > books closed through*) after reconciling. |
| Quarterly | System administrator | The disaster-recovery drill (`BACKUP_AND_RESTORE.md`); review users and roles; remove leavers. |
| Every deployment | Developer | `deploy/deploy.sh <ref>` (`DEPLOYMENT.md`). |

## When a monitor row is red

| Row | Meaning | Do |
| --- | --- | --- |
| Database backup / Off-site copy | No good backup in 26 h, or the newest one is not off-site | `php artisan erp:backup` by hand and read its error. Usually the off-site disk credentials or free space. |
| Restore test | The newest backup could not be loaded | Treat as **no backup**. `php artisan erp:backup:test-restore` and read the message; take a fresh backup. |
| **Reconciliation** | A ledger disagrees with its documents | Stop and investigate before anyone keeps posting. `php artisan erp:reconcile` names the line: *Stock ledger* (stock differs from the sum of the ledger), *Journal balance* (an entry does not balance), *Receivables* / *Payables* (ledger differs from invoices and payments). This cannot happen through the application; look for a manual database edit, a failed restore or a bug. Restore the last good backup to a spare server and compare. |
| Scheduler | Cron is not running | Check the `schedule:run` cron line. Nothing that is scheduled (alerts, postings, backups) is happening. |
| Queue worker | Jobs wait, or some failed | `supervisorctl status`; restart the worker; `php artisan queue:retry all` after fixing the cause. |
| Application errors | More than 20 unexpected errors in 24 h (`MONITOR_MAX_ERRORS_PER_DAY`) | The row shows the kind and place of the latest error. Read `storage/logs/laravel-*.log` around that time. |
| Disk space | Under 10 % free | Old logs, backups beyond retention, uploads. |
| Production settings | `APP_DEBUG` on | Turn it off at once; errors are showing internals to visitors. |

`erp:monitor` notifies everyone who holds *Backups* view once a day per set of problems. The monitor does not replace an external uptime check: point one at `GET /health`.

## Commands

| Command | Use |
| --- | --- |
| `erp:create-owner {name} {email}` | First Owner account. |
| `erp:preflight` | Is this server safe to serve production? Run before and after deploying. |
| `erp:backup`, `erp:backup:test-restore` | Backup now; prove the newest one restores. |
| `erp:reconcile` | Do stock, journal, receivables and payables agree with their documents? Runs nightly at 03:00. |
| `erp:monitor` | The status page as an alarm (every 15 min). |

## Making changes safely

- Work one phase or change at a time on a branch; the Pest suite (`vendor/bin/pest`) and Pint (`vendor/bin/pint`) must pass. `tests/Feature/Hardening` guards the things that must never break: every route and page needs a sign-in, every resource a policy, the ledgers reconcile after a full trading cycle, and list pages stay free of N+1 queries.
- A new module needs: a `Module` enum case, a policy registered in `AppServiceProvider`, a role grant in `RoleSeeder` (the audit test fails otherwise), and a line in `IMPLEMENTATION_STATUS.md`.
- Never delete or edit ledger rows (inventory transactions, journal lines). Add a reversal. The reconciliation exists to catch exactly this.
- Configuration (thresholds, prices, targets, approval limits) is data, edited in the application. Do not hard-code it.

## Known limits

Listed in the Phase 20 and Phase 19 entries of `IMPLEMENTATION_STATUS.md`. The important ones: uploaded files are not part of the database backup; the recovery point is up to 24 hours; backups are not encrypted by the application (use an encrypted private bucket).
