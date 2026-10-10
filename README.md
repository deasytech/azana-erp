# Azana Farms ERP

Integrated farm management and agribusiness system for Integrated Princess Azana Farms Ltd: pig breeding, semen laboratory, feed mill, slaughter and meat processing, stores, sales and finance in one application, plus the public company website and the API for the mobile app.

One Laravel application, one database, three faces:

| Address (production) | What it is |
|---|---|
| `www.azanafarms.com` | Public website |
| `erp.azanafarms.com` | The management application (Filament) |
| `api.azanafarms.com/api/v1` | Mobile API with offline sync (reference at `/docs`) |

Built with Laravel 13, PHP 8.3, Filament 5, Livewire, Sanctum, MySQL 8, Vite and Tailwind 4. Tests use Pest on in-memory SQLite.

## What is in it

Domain code lives in `app/Domain/<Module>` (actions, models, events); the screens in `app/Filament` only call those actions.

Animals and identification · breeding, farrowing and litters · health, vaccination, quarantine and mortality · production batches and growth · feed formulas and feed mill · semen collection, QC and release · inventory ledger and stock counts · purchasing and supplier payments · sales, credit and customer payments · slaughter, carcasses and meat · finance (journals, budgets, cash flow, profitability) · tasks, alerts and approvals · reporting and KPI targets · traceability from semen to customer · data import · backup, reconciliation and monitoring · mobile API · public website.

## Local setup

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_* in .env (MySQL) or keep SQLite, then:
php artisan migrate
php artisan db:seed              # roles, standard master data, chart of accounts, and demo users (local only)
npm install && npm run build     # or: npm run dev
php artisan serve
```

Demo accounts are created only in the `local` and `testing` environments, one per role, as `<role-name>@azana.test` (for example `ownerdirector@azana.test`). Their password is `password`; they exist for local use only. In production create the first owner with `php artisan erp:create-owner "Full Name" owner@example.com`.

### Practice data

To try the whole system with three months of realistic activity:

```bash
php artisan db:seed --class=DemoDataSeeder
```

It refuses to run in production or when animals already exist. When real data is about to start, sign in as the Owner and use **Administration > Go-live data reset**: it clears the practice records (after an optional backup), keeps users, roles, settings, lists and the chart of accounts, and switches the system to live mode, after which the reset turns itself off.

## Tests and code style

```bash
vendor/bin/pest tests/Feature/Sales     # one folder at a time
vendor/bin/pint                          # code style
```

Run folders separately: one process over the whole suite has not been completing in reasonable time on the author's machine.

## Useful commands

| Command | Purpose |
|---|---|
| `php artisan erp:preflight` | Production readiness checks (debug off, https, queue, off-site backup, folders...) |
| `php artisan erp:backup` / `erp:backup:test-restore` | Database backup, off-site copy and restore proof |
| `php artisan erp:reconcile` | Stock, journal, receivables and payables against their documents |
| `php artisan erp:monitor` | The checks shown on **Backups & monitoring** |

## Documentation

* `docs/ERP_MASTER_PLAN.md`, `ARCHITECTURE.md`, `DATABASE_ARCHITECTURE.md`, `DOMAIN_RULES.md`: what the system is and the rules it enforces.
* `docs/IMPLEMENTATION_STATUS.md`: what has been built, tested and decided, phase by phase.
* `docs/API.md`, `OFFLINE_SYNC.md`: the mobile API and its offline contract.
* `docs/SECURITY.md`, `BACKUP_AND_RESTORE.md`, `DATA_IMPORT.md`: security, backups, loading existing records.
* `docs/DEPLOYMENT.md`, `LAUNCH_CHECKLIST.md`, `UAT.md`, `HANDOVER.md`: going live and running it.
* `docs/deployment/`: the Hostinger VPS preparation pack (audit, blocker plan, server guide, environment, DNS and e-mail cutover, verification). The application has **not** been deployed yet.
* `deploy/`: nginx, PHP, Supervisor, cron, `.env` template and `deploy.sh`.

## Working in this repository

Read `CLAUDE.md` first: read the master plan and architecture documents, work on the current phase only, run tests, and update `docs/IMPLEMENTATION_STATUS.md`.
