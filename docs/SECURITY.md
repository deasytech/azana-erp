# Security and Governance

## Authentication

Require individual user accounts.

Use strong password policies.

Sensitive roles should support/require 2FA.

## Authorization

Every module supports permissions conceptually for:

- view
- create
- edit
- approve
- delete/cancel
- export
- print

Use role-based permissions.

## Sensitive Data

Financial and administrative information must be restricted to authorized personnel.

## Audit Trail

Audit:
- who created
- when
- device/context where available
- who edited
- what changed
- previous value
- new value
- approval

Prioritize:
- inventory
- finance
- sales
- animal movement
- mortality
- semen
- slaughter
- stock adjustments

## Data Protection

Implement:
- encrypted transport
- secure session handling
- CSRF protection
- secure file uploads
- authorization policies
- rate limiting for APIs
- validation
- safe logging
- secret management

Never log:
- passwords
- payment secrets
- access tokens
- private credentials

## Backups

Required:
- automatic database backups
- daily backups
- off-site/cloud backups
- retention policy
- disaster recovery process

Backups must be tested periodically, not merely created.

## Record Integrity

Historical operational and financial transactions should not be physically deleted.

Use cancellation, reversal and correction workflows.

## Imports

Imported historical data must be validated and reported.

Never overwrite production records silently during import.

## Security review (Phase 20)

Reviewed and covered by automated tests (`tests/Feature/Hardening/AuthorizationAuditTest.php`) so a later change cannot quietly undo them:

- **Sign-in:** every route outside the public website, the API documentation, the health check and the sign-in endpoints sits behind authentication; every `/api/v1` route except login needs a token; the mobile login is rate limited per address and per account.
- **Authorization:** every Filament resource's model has a policy; a user holding no permission can open no resource, no page except the home page, and gets nothing from any policy's `viewAny`/`create`; the Owner cannot delete users or edit the audit trail; operational roles hold no finance, user, role, import or backup permission; a Farm Worker holds no approve, delete or export permission; the roles that handle money, semen release and everything need two-factor.
- **Separation of duties:** the person who requests a stock adjustment, count, journal, purchase or large payment cannot be the one who approves it (domain rule, tested in each module).
- **Data:** SQL is built with bound parameters (the few raw expressions contain no user input); imports never overwrite and are validated by a dry run; ledgers are append-only and the nightly reconciliation (`erp:reconcile`) detects tampering; errors are counted without their messages so customer data does not reach the monitor.
- **Transport and sessions:** https only, secure and http-only same-site cookies, HSTS and clickjacking/MIME headers (see `deploy/nginx`).

Not done and why: a Content-Security-Policy header (Filament and Livewire use inline scripts, so a useful policy needs a nonce set-up of its own; add it behind a report-only header first); automatic encryption of backups (use an encrypted private bucket); an external penetration test (recommended before opening the mobile API to the public internet at scale).

