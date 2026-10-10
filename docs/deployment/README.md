# Production deployment pack (Hostinger VPS)

**Status: prepared, not deployed.** Nothing here has been run on a server, DNS has not been changed and no production credential exists in the repository.

Read in this order:

1. [PRODUCTION_READINESS_AUDIT.md](PRODUCTION_READINESS_AUDIT.md) - what was inspected, what blocks launch, what was actually tested.
1a. [BLOCKER_RESOLUTION_PLAN.md](BLOCKER_RESOLUTION_PLAN.md) - **start here for next steps**: off-site storage options, Syskay SMTP fields, VPS checklist, nginx and `deploy.sh` review, the chart-test finding, API host check, and the decisions needed for staging.
2. [HOSTINGER_VPS_GUIDE.md](HOSTINGER_VPS_GUIDE.md) - target architecture and the step-by-step build of the server.
3. [PRODUCTION_ENVIRONMENT_CHECKLIST.md](PRODUCTION_ENVIRONMENT_CHECKLIST.md) - every `.env` value.
4. [DNS_AND_EMAIL_CUTOVER.md](DNS_AND_EMAIL_CUTOVER.md) - moving the website without touching the company's e-mail.
5. [VERIFICATION_CHECKLIST.md](VERIFICATION_CHECKLIST.md) - the acceptance test plan (with what has and has not been run).

Existing documents that still apply and are not repeated: `docs/DEPLOYMENT.md` (the routine and the deploy script), `docs/BACKUP_AND_RESTORE.md` (backups, retention, the restore drill), `docs/OFFLINE_SYNC.md`, `docs/SECURITY.md`, `docs/LAUNCH_CHECKLIST.md`.

Files in `deploy/`: `nginx/azana-bootstrap.conf`, `nginx/azana.conf`, `nginx/snippets/*.conf`, `php/99-azana.ini`, `supervisor/azana-worker.conf`, `crontab.example`, `env.production.example`, `deploy.sh`.
