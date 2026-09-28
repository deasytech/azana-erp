# Architecture

## Decision

Use a modular Laravel monolith with Filament as the internal ERP interface, Livewire for custom interactive workflows, and an API layer for mobile/offline clients.

Do not create separate Laravel codebases for ERP and public website in version 1.

## Application Surfaces

### Internal ERP
`erp.azanafarms.com`

Filament-based administration and operations.

### Public Website
`www.azanafarms.com`

Custom Laravel Blade/Livewire frontend.

### API
`api.azanafarms.com`

Can initially be routed from the same Laravel application. Separate deployment is optional later.

## Layering

### Presentation
- Filament Resources
- Filament Pages
- Filament Widgets
- Livewire components
- Blade
- API Controllers

### Application
- Actions
- Commands
- DTOs
- Application Services

### Domain
- Models
- Value objects
- Domain services
- Policies
- Domain events
- Business rules

### Infrastructure
- Eloquent persistence
- Files
- queues
- notifications
- external integrations

Presentation code must call application/domain code rather than owning business rules.

## Multi-interface rule

The same operation must produce the same result whether initiated by:

- Filament
- Livewire
- API
- future mobile application
- background job

For example, `RecordAnimalMovement` must not have one implementation for Filament and another for mobile.

## Database

Use one central relational database.

The database must represent relationships between:

Farm
Building
Pen
Animal
Litter
Breeding
Health
Feed
Inventory
Semen
Sales
Slaughter
Meat
Finance

## Identifiers

Use stable internal primary keys plus public/business identifiers.

Examples:

- Animal: `IPA-SOW-0001`
- Litter: `IPA-2026-SOW001-L01`
- Semen batch: `IPA-SM-DUR-20260904-001`
- Production order: `FM-000123`

Use UUID/ULID identifiers where useful for offline-created transactions. Business numbers remain human-readable.

## Financial integrity

Use decimal or integer minor-unit representation consistently. Never use PHP floating-point arithmetic for money.

## Transactions

Use database transactions for workflows that modify multiple related records.

Examples:

Feed production:
1. validate production order
2. calculate/confirm consumption
3. post raw-material inventory transactions
4. create finished feed batch
5. post finished-feed inventory
6. calculate production cost
7. finalize production order

If any critical step fails, the transaction must roll back.

## Auditability

Critical records require:

- actor
- timestamp
- device/context where available
- old values
- new values
- approval
- reason where applicable

Do not rely only on generic Laravel timestamps for audit requirements.

## Soft deletion

Use soft deletion only where business-safe.

Do not soft-delete historical transactions as a substitute for reversal.

Transactions should be immutable after posting, with corrections represented as reversal/adjustment transactions.

## Reporting

Reports should read from normalized operational/transaction data.

Do not create duplicated shadow data solely to make dashboards easy unless justified by measured performance needs.

---

## Claude Constraints

- Do not introduce microservices.
- Do not create separate databases per module.
- Do not place business logic exclusively in Filament.
- Do not hard-code production assumptions.
- Do not use floating point for financial calculations.
- Do not delete historical operational transactions.
- Do not bypass inventory ledger posting.
- Do not bypass approval rules.
