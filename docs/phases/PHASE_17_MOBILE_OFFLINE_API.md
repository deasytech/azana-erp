# Phase 17 - Mobile API, Offline Sync and Worker Quick Entry

## Objective
Implement the mobile operational layer.

## Tasks
- Authentication API.
- Mobile permissions.
- Animal lookup.
- QR/barcode scan endpoints.
- Quick-entry endpoints.
- Local sync contract.
- Idempotency.
- Conflict handling.
- Sync queue.
- Sync status.
- Worker task interface.
- Offline tests.
- API documentation.

## Required Quick Actions
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

## Acceptance Criteria
- [x] Core field transactions can be captured without internet.
- [x] Sync resumes automatically.
- [x] Duplicate submissions do not duplicate transactions.
- [x] Conflicts are surfaced.
- [x] Mobile uses the same domain actions as web.
