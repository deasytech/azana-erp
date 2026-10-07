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

## Implemented contract (Phase 17)

The endpoints, mutation format, statuses and the twelve quick actions are specified in [API.md](API.md). In short:

- Every mutation carries a device-made UUID `client_id` (the idempotency key), the device's `occurred_at`, and a payload.
- The server stores each one in `sync_mutations` (client id, device id, user, attempted/synced times, status, error, server record) and answers with `accepted`, `rejected`, `conflict` or `failed`. A final answer is returned unchanged if the same `client_id` is sent again; only `failed` is run again.
- Conflicts are never applied over newer data. They wait in the web app (*Tasks & alerts > Mobile sync log*) for a supervisor with `mobile.edit`, who marks them reviewed with a note.
- Reference lists are fetched with a version so the device can work offline between syncs.
- The sync layer calls the same domain actions as the web screens; the rules (and their conflict/rejection codes) live there.

