# Integrated Princess Azana Farms ERP
## Master Development Plan for Claude Code

**Project:** Integrated Princess Azana Farms Ltd ERP  
**Source specification:** Integrated Princess Azana Farms ERP, Version 1.0, September 2026  
**Target stack:** Laravel + Livewire + Filament + MySQL  
**Architecture:** Modular monolith, API-ready, mobile-first, offline-capable  
**Primary objective:** Establish one digital source of truth for the entire farm and agribusiness.

---

## 1. Product Definition

This is an integrated farm and agribusiness ERP, not merely a piggery CRUD application.

The system must connect:

Genetics -> Semen -> Breeding -> Pig Production -> Feed Mill -> Raw Materials -> Animal Health -> Growing/Finishing -> Sales/Slaughter -> Meat Processing -> Customers -> Finance -> Management Intelligence

The central business chain is:

Animal production -> Inputs -> Cost -> Output -> Sale -> Revenue -> Profit

For processed products:

Animal -> Feed -> Slaughter -> Carcass -> Meat Product -> Customer -> Revenue

For genetics:

Boar -> Semen Collection -> Semen Batch -> Customer/Sow -> Piglet -> Growth -> Sale

The source specification explicitly requires a single integrated application and says the modules must not become isolated applications.

---

## 2. Architecture Decision

### 2.1 Recommended architecture

Build **one Laravel modular monolith**.

Use:

- Laravel for the application and domain/backend
- Filament for internal ERP administration and operational interfaces
- Livewire for interactive web workflows and dashboards
- Laravel API endpoints for mobile clients and future integrations
- MySQL for the central transactional database
- Queues/jobs for asynchronous processing
- Notifications for alerts
- Storage abstraction for photos/documents
- Laravel scheduler for recurring tasks and alerts
- Role/permission system for access control

Do NOT build two independent Laravel applications for the ERP and public website at this stage.

Recommended deployment topology:

    www.azanafarms.com
        Public website

    erp.azanafarms.com
        Internal ERP / management application

    api.azanafarms.com
        Mobile/API surface, when separated routing becomes useful

These can initially run from the same Laravel codebase and database.

### 2.2 Why Filament is appropriate

The specification contains a very large amount of structured operational data:

- animals
- pens
- breeding events
- health records
- inventory
- suppliers
- purchases
- semen batches
- QC
- sales
- slaughter records
- meat inventory
- finance
- users
- approvals
- reports
- configuration

Filament is therefore highly suitable for the internal ERP interface.

However:

**Filament must be treated as a presentation/application interface, not as the business domain.**

Do not put critical business rules only inside Filament Resources or Pages.

Business logic belongs in reusable Actions/Services/Domain classes.

Example:

    app/Domain/Animal/Actions/RegisterAnimal.php
    app/Domain/Breeding/Actions/RecordService.php
    app/Domain/Inventory/Actions/PostInventoryTransaction.php
    app/Domain/Feed/Actions/CompleteFeedProduction.php
    app/Domain/Semen/Actions/ReleaseSemenBatch.php
    app/Domain/Sales/Actions/ConfirmSale.php

Filament actions, Livewire components, API controllers, jobs and future mobile clients should call these same application services.

### 2.3 Do not build a separate custom admin from scratch

There is no advantage in recreating:

- tables
- filters
- forms
- authorization
- resource pages
- dashboards
- bulk actions
- exports
- notifications
- relation managers

when Filament already provides the internal ERP foundation.

Use custom Livewire/Blade interfaces where the workflow requires a specialized experience.

Examples:

- worker quick-entry screens
- farm operational dashboards
- breeding calendar
- traceability explorer
- management cockpit
- mobile-responsive task interface
- public website
- customer-facing portal

---

## 3. Critical Architectural Principles

### Principle 1: One database

Do not create disconnected databases for:

- pigs
- semen
- feed
- inventory
- sales
- slaughter
- finance

They must share identifiers and transaction records.

### Principle 2: Every physical event creates a digital transaction

Examples:

Pig born -> animal registered -> weighed -> vaccinated -> moved -> fed -> sold/slaughtered

Feed produced -> raw materials consumed -> finished feed created -> feed consumed by animals

Semen collected -> evaluated -> QC -> released -> inventory -> sold/used internally

Slaughter -> carcass -> meat batch -> inventory -> sale

### Principle 3: Inventory is a ledger

Never make inventory reliable by simply changing `stock_quantity`.

Use immutable inventory transactions.

Conceptually:

Opening
+ Purchases
+ Production
+ Returns
+ Transfers In
- Consumption
- Sales
- Transfers Out
- Wastage
- Adjustments
= Closing Stock

Corrections must create reversal/adjustment transactions and audit records.

### Principle 4: Historical records are not physically deleted

Important operational records should be:

- cancelled
- reversed
- corrected
- archived where appropriate

with an audit trail.

### Principle 5: Configuration, not hard-coding

The following must be database-configurable:

- breeds
- feed types
- formulations
- vaccinations
- medicines
- pens
- buildings
- production targets
- semen prices
- pig prices
- meat prices
- tax rates
- payment methods
- customer types
- supplier types
- units of measure
- stock thresholds
- alert thresholds
- production assumptions
- roles
- approval limits

### Principle 6: Traceability first

Every major entity should retain links to its source and downstream outputs.

A meat batch must be traceable back through:

Meat -> slaughter -> pig -> finisher batch -> pen -> feed -> feed batch -> raw materials -> suppliers -> litter -> sow -> boar -> semen batch

### Principle 7: Offline mobile capture is a first-class requirement

Do not postpone offline architecture until after the database is complete.

The API and transaction model must support:

- local capture
- client-generated UUIDs
- idempotency
- sync status
- conflict handling
- retry
- server acknowledgement

### Principle 8: Money must use safe monetary representation

Do not use floating point for financial values.

Use integer minor units or a precise decimal strategy consistently throughout the financial subsystem.

### Principle 9: Domain events

Important business events should be represented explicitly.

Examples:

- AnimalRegistered
- AnimalMoved
- BreedingServiceRecorded
- PregnancyConfirmed
- FarrowingRecorded
- WeaningRecorded
- MortalityRecorded
- FeedProductionCompleted
- InventoryTransactionPosted
- SemenBatchCollected
- SemenBatchReleased
- SaleConfirmed
- SlaughterCompleted
- MeatProduced
- PaymentReceived

Use events/jobs/notifications where appropriate, but do not introduce unnecessary event-sourcing complexity.

---

## 4. Proposed Repository Structure

    app/
      Domain/
        Farm/
        Animal/
        Breeding/
        Litter/
        Health/
        Feed/
        Inventory/
        Procurement/
        Semen/
        Sales/
        Slaughter/
        Meat/
        Finance/
        Customer/
        Supplier/
        Staff/
        Tasks/
        Reporting/
        Assets/
        Biosecurity/
        Traceability/
        System/

      Actions/
      Services/
      Data/
      Enums/
      Events/
      Listeners/
      Jobs/
      Notifications/
      Policies/
      Models/
      Support/

      Filament/
        Admin/
        Resources/
        Pages/
        Widgets/

      Livewire/
        Public/
        Operations/
        Management/

      Http/
        Controllers/
          Api/
        Requests/
        Resources/

    database/
      migrations/
      seeders/
      factories/

    routes/
      web.php
      api.php

    resources/
      views/
      js/

    docs/
      ERP_MASTER_PLAN.md
      ARCHITECTURE.md
      DATABASE_ARCHITECTURE.md
      DOMAIN_RULES.md
      SECURITY.md
      OFFLINE_SYNC.md
      IMPLEMENTATION_STATUS.md
      phases/

---

## 5. Development Order

The original specification proposes six broad phases. For implementation with Claude Code, those six phases should be decomposed into smaller engineering phases.

### Phase 01
Foundation and architecture

### Phase 02
Identity, roles, permissions and audit

### Phase 03
Farm structure and master data

### Phase 04
Animal registry and lifecycle

### Phase 05
Breeding, farrowing, litters and piglets

### Phase 06
Health, veterinary, mortality, culling and biosecurity

### Phase 07
Weights, growers, finishers and feed consumption

### Phase 08
Inventory transaction engine and procurement

### Phase 09
Feed formulation and feed mill manufacturing

### Phase 10
Semen production, laboratory QC and semen inventory

### Phase 11
Customers, semen sales and pig sales

### Phase 12
Slaughter, carcass, meat processing and meat inventory

### Phase 13
Meat sales and end-to-end traceability

### Phase 14
Finance, costing, cash flow, budgets and profitability

### Phase 15
Tasks, alerts, notifications and approvals

### Phase 16
Dashboards, reporting, exports and management intelligence

### Phase 17
Mobile API, offline sync and worker quick entry

### Phase 18
Public website and customer-facing functionality

### Phase 19
Data import, migration, backup and deployment

### Phase 20
Hardening, testing, performance and production launch

Advanced forecasting, RFID, automated weighing, IoT, AI-assisted management and advanced integrations are explicitly secondary features and should follow stabilization of the core ERP.

---

## 6. Phase Dependency Rule

Claude must never skip dependencies.

Examples:

- Inventory must exist before feed production can correctly consume raw materials.
- Animal identity must exist before breeding/health/movement transactions.
- Semen batch/QC must exist before semen sales.
- Slaughter records must exist before meat inventory.
- Meat inventory must exist before meat sales.
- Financial posting should consume confirmed operational transactions rather than duplicate operational data entry.
- Offline mobile sync must use the same domain transaction rules as the web ERP.

---

## 7. Definition of Done

A phase is complete only when:

1. Database migrations exist.
2. Models and relationships exist.
3. Business rules are implemented outside UI-only code.
4. Authorization is enforced.
5. Audit requirements are implemented where applicable.
6. Filament/admin interface exists where required.
7. API support exists where required.
8. Validation exists.
9. Automated tests exist for critical business rules.
10. Seed/demo data exists where useful.
11. Reports/widgets are implemented where required.
12. The phase acceptance criteria pass.
13. No unrelated modules are changed.
14. Documentation/status is updated.

---

## 8. Claude Operating Rules

Before coding:

1. Read this file.
2. Read the relevant phase file.
3. Read ARCHITECTURE.md.
4. Read DOMAIN_RULES.md.
5. Read DATABASE_ARCHITECTURE.md.
6. Inspect existing code before creating new code.
7. Search for existing models/services/components before duplicating functionality.

During coding:

- Work only within the active phase.
- Do not silently redesign completed phases.
- Do not hard-code farm assumptions that the specification says must be configurable.
- Do not create duplicate business logic in Filament and API layers.
- Prefer reusable application services/actions.
- Use database transactions for multi-record business operations.
- Preserve historical records.
- Do not delete financial/inventory/animal history.
- Add tests for business rules.
- Keep migrations reversible where practical.
- Do not introduce a new package unless there is a documented reason.
- Do not replace Laravel/Filament conventions without a clear architectural reason.

After coding:

- Run relevant tests.
- Run static analysis/formatting if configured.
- Review database integrity.
- Review authorization.
- Review audit implications.
- Update IMPLEMENTATION_STATUS.md.
- Report files changed and remaining work.

---

## 9. MVP Boundary

The first production release should prioritize the operational backbone specified in the document:

- animal identification
- breeding
- farrowing
- weaning
- movement
- weight tracking
- health
- mortality
- feed consumption
- raw material inventory
- feed production
- semen production
- semen inventory
- semen sales
- pig sales
- slaughter
- meat inventory
- meat sales
- customers
- suppliers
- basic finance
- dashboard
- reports
- permissions
- audit trail
- offline mobile data capture

Advanced RFID, IoT, forecasting, AI-assisted management, automated procurement, advanced accounting integrations and similar features come later.

---

## 10. Success Criteria

The final system must allow management to answer operational, financial and traceability questions without manually combining spreadsheets.

The strongest acceptance test is:

> Management can trace revenue and major costs back to the operational activity that generated them.

The application should ultimately provide:

- current herd visibility
- individual animal history
- breeding performance
- feed and raw-material visibility
- semen production and inventory
- slaughter and carcass yield
- meat inventory
- sales and receivables
- costs and profitability
- cash position
- target vs actual
- complete traceability
- audit history
- management alerts
- decision-support information
