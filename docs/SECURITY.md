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
