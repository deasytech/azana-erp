# Production launch checklist

Production is declared ready only when **every box in the launch gate is ticked by a named person on a named date**. The rest of this page says how to check each one. Nothing here is a judgement call: each item is a command or a screen with a pass/fail answer.

## Launch gate

| # | Gate | How to check | Passes when |
| --- | --- | --- | --- |
| 1 | Critical tests pass | `vendor/bin/pest` on the release commit | No failures. `tests/Feature/Hardening` (authorization audit, reconciliation, farm-to-customer chain, performance, error monitoring) is part of the run. |
| 2 | Backup restore verified | `php artisan erp:backup` then `php artisan erp:backup:test-restore`; then the quarterly drill in `docs/BACKUP_AND_RESTORE.md` on a spare server | Both succeed, *Backups & monitoring* shows Database backup, Off-site copy and Restore test all OK, and the drill restored the site on another machine. |
| 3 | Authorization verified | `vendor/bin/pest tests/Feature/Hardening/AuthorizationAuditTest.php`; then sign in as each role (UAT, step 4) | The audit passes and each role sees only its own menus. |
| 4 | Inventory ledger reconciles | `php artisan erp:reconcile` | *Stock ledger* is OK: stock on hand equals the sum of the ledger for every item, store and batch. Then a physical stock count in the main store is approved with no unexplained variance. |
| 5 | Financial balances reconcile | `php artisan erp:reconcile`; *Finance > Trial balance* | *Journal balance*, *Receivables* and *Payables* are OK and the trial balance is balanced. Opening balances were entered and agreed with the accountant's own records. |
| 6 | Traceability works | UAT scenario 6 | A meat batch traces to its supplier and semen batch, and forward to the customer's invoice. |
| 7 | Mobile sync works | UAT scenario 7 on a real phone, in aeroplane mode and back | Entries made offline arrive once each after reconnecting; a deliberately conflicting entry lands in *Mobile sync log* for review. |
| 8 | Production monitoring active | *Administration > Backups & monitoring* | Every row is OK (a documented warning is acceptable only for *Restore test* age before its first Sunday). An external uptime monitor watches `/health` and its alert has been tested by stopping the queue worker for ten minutes. |

## Before go-live day

- [ ] `php artisan erp:preflight` prints no FAILED line.
- [ ] `.env` follows `deploy/env.production.example`: `APP_ENV=production`, `APP_DEBUG=false`, https, `QUEUE_CONNECTION=database` or redis, a real cache store, `BACKUP_OFFSITE_DISK` set.
- [ ] Cron line and Supervisor worker installed (`deploy/crontab.example`, `deploy/supervisor/`). *Scheduler* and *Queue worker* show OK.
- [ ] Database account has only the rights the application needs; the restore-test account may create and drop `BACKUP_RESTORE_TEST_DATABASE`.
- [ ] Demo and test users do not exist. The Owner has two-factor on. Every Owner, General Manager, Accountant and Semen Laboratory Manager account has two-factor on (required by their role).
- [ ] Each person has their own account and role; no shared logins.
- [ ] Historical data imported through *Data imports*, with the problems file for every kind empty.
- [ ] Opening stock counted and imported; opening cash, bank, receivables and payables entered as finance journals.
- [ ] Price lists, farm settings (gestation, weaning, alert thresholds, credit rule, valuation method) and approval thresholds reviewed with management. These are configuration, not code.
- [ ] Uploaded files (`storage/app`) are included in the server's own backup, since the database backup does not hold them.
- [ ] Staff trained on their role's UAT scenarios; the handover document (`docs/HANDOVER.md`) read by the person who looks after the system.

## Go-live day

1. Take a backup (`php artisan erp:backup`) and note its time. This is the rollback point.
2. Deploy with `deploy/deploy.sh <release-ref>`; it stays in maintenance mode if any step fails.
3. Run `erp:preflight`, `erp:reconcile` and open *Backups & monitoring*. All clear.
4. Sign in as the Owner and as one worker on a phone. Enter one real record each.
5. Announce the go-live. From now on the old spreadsheets are read-only.

## First week

- Daily: look at *Backups & monitoring* and the *Approvals* and *Alerts* pages.
- After day 1 and day 7: run `erp:reconcile`, count one store and compare with the system.
- Write down every question staff ask; each one is either training or a missing feature.
- After the first Sunday: confirm the weekly restore test passed.

## Known limits at launch

See the Phase 20 entry in `docs/IMPLEMENTATION_STATUS.md` (restore test does not cover uploaded files; 24-hour recovery point with nightly backups; real S3, nginx and the deploy script were checked only for syntax, not on a live server).
