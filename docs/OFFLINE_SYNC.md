# Offline Mobile and Synchronization Architecture

## Requirement

Farm staff must be able to capture operational data when connectivity is unavailable.

Examples:
- animal registration
- QR/tag scan
- weights
- feeding
- treatments
- vaccination
- breeding
- farrowing
- weaning
- mortality
- movements
- feed production
- stock counts
- slaughter records
- tasks

## Design

The mobile application should use a local data store.

Each locally-created mutation receives a client-generated UUID/ULID and idempotency key.

The sync engine sends pending mutations to the API.

Server response:
- accepted
- rejected
- requires review
- conflict

## Idempotency

Repeated submission of the same client transaction must not duplicate the business event.

Use a unique idempotency key.

## Conflict Rules

Conflicts must be explicit.

Examples:
- same animal moved to two locations
- stock count based on stale quantity
- record edited by another user
- animal already marked dead
- semen batch already released/destroyed

Do not silently overwrite the server record.

## Sync Log

Maintain sync metadata:
- client transaction ID
- device ID
- user
- attempted_at
- synced_at
- status
- error
- server record ID

## Quick Entry

The mobile interface should minimize typing.

Primary actions:
- Add Birth
- Record Weight
- Record Feed
- Record Treatment
- Record Vaccination
- Record Mortality
- Move Pigs
- Record Service
- Record Farrowing
- Record Weaning
- Stock Count
- Complete Task

## API Rule

Mobile endpoints must call the same application/domain actions used by web interfaces.

Do not create a second business logic implementation for mobile.
