# Implementation Status

## Current Phase
Phase 07 - Weights, Growers, Finishers and Feed Consumption (not started)

## Completed

### Phase 01 - Foundation (2026-09-28)
- Commit/reference: `7b60bfb` on `dev`
- Tests: `vendor/bin/pest` - 5 passed (boot, /health, Filament login, Livewire, DomainException rendering)
- What was done:
  - Installed `filament/filament` ^5.9 (brings Livewire); admin panel at `/admin` (`app/Providers/Filament/AdminPanelProvider.php`)
  - Domain directory skeleton under `app/Domain/*` (per master plan), plus `app/Livewire/{Public,Operations,Management}`, `app/Http/Controllers/Api`, `app/Support`
  - `App\Domain\System\Exceptions\DomainException` - base business-rule exception, rendered as JSON 422
  - `App\Domain\System\Actions\RunHealthChecks` + `GET /health` (database, cache, queue, storage; 503 when degraded). Failure details are generic (exceptions are reported to logs, not exposed). The queue check probes backend reachability only (`Queue::size()`); it does not verify a worker is running. `/health` bypasses session middleware so it still returns 503 if the session store is down. Laravel's `/up` is retained.
  - Removed skeleton example tests; added `tests/Feature/FoundationTest.php`
- Configuration: MySQL (`azana_erp`), database queue/cache/session drivers, `local` filesystem disk; `storage:link` created. Tests run on in-memory SQLite (phpunit.xml).
- Known issues: none. No queue worker is run automatically; use `php artisan queue:work` (or `composer dev`).
- Migration notes: no new migrations (Filament adds none). Existing users/cache/jobs migrations applied.
- Follow-up: no admin user exists yet - Phase 02 owns users/roles/permissions and Filament panel access control (the panel is currently gated only by Filament defaults).

### Phase 02 - Identity, Authorization and Audit (2026-09-29)
- Commit/reference: see git log on `dev` ("Implement Phase 02")
- Tests: `vendor/bin/pest` - 26 passed (added `tests/Feature/Identity/IdentityTest.php`; `RefreshDatabase` now enabled for Feature tests in `tests/Pest.php`)
- New package (documented reason): `spatie/laravel-permission` ^8.3 - database-configurable roles and permission matrix (master plan Principle 5). Audit trail and login activity are built in-house, no package.
- What was done:
  - Users: `is_active`, `last_login_at/ip`, encrypted 2FA secret + recovery codes; `User` implements `FilamentUser` (panel access = active AND has a role), new users default active
  - Roles: 12 required roles seeded (`RoleSeeder`); `App\Models\Role` extends Spatie's with `requires_two_factor` and `description`. Seeding never overwrites edits to existing roles.
  - Permission matrix: `App\Enums\Module` x `App\Enums\PermissionAction` -> permissions named `{module}.{action}` (view/create/edit/approve/delete/export/print). Later phases add their modules to the `Module` enum and re-run `PermissionSeeder`. `ModulePolicy` maps policy methods onto the matrix; Filament navigation follows policies, so users only see permitted modules.
  - Owner bypass: `Gate::before` in `AppServiceProvider` lets an active Owner/Director (`Role::OWNER`) pass every `{module}.{action}` permission check, including modules and permissions added in later phases, even if the role's grants are edited. Only permission checks are bypassed - policy rules below still apply to the Owner. Inactive owners are locked out.
  - Policies: users can never be deleted (deactivate instead); roles in use cannot be deleted; audit log and login activity are view/export only.
  - 2FA: Filament app authentication (TOTP + recovery codes) on the panel profile page; enforced for any user holding a role with `requires_two_factor` (seeded on: Owner/Director, General Manager, Semen Laboratory Manager, Accountant).
  - Audit foundation: append-only `audit_logs` (actor, event, old/new values, reason, approved_by, IP, user agent, device id from `X-Device-Id`, URL). `App\Domain\System\Actions\RecordAudit` is the single write path; `Auditable` trait audits create/update/delete on models (applied to `User`, `Role`). Secrets are redacted. Role assignment and permission changes are audited explicitly. Models reject update/delete.
  - Login/session activity: `login_activities` (login, logout, failed, lockout) via auto-discovered `App\Listeners\RecordLoginActivity`; passwords are never stored.
  - Password policy: min 12, mixed case, numbers, symbols, breach check in production (`Password::defaults()`).
  - Filament (Administration group): Users, Roles (with permission matrix), Audit log, Login activity.
  - `php artisan erp:create-owner {name} {email}` creates the first Owner (prompts for password). `DatabaseSeeder` creates one demo user per role only in local/testing (`<role-slug>@azana.test`, factory password) - never in production.
- Migration notes: 3 new migrations (Spatie permission tables, identity columns on users/roles, audit_logs + login_activities). All reversible.
- Known issues / notes:
  - Operational roles have no operational permissions yet (Owner passes all checks via the bypass; General Manager can view users/audit/login activity). Each later phase grants its module permissions to the relevant roles.
  - `Auditable` is applied only to identity models so far; later phases must apply it to inventory, finance, sales, animal movement, mortality, semen, slaughter and stock-adjustment models (SECURITY.md priorities).
  - Approval *workflow* (thresholds, approvers) is Phase 15; `approved_by` and the `approve` permission exist already.
  - 2FA is enforced in the Filament panel only; API authentication (Phase 17) needs its own 2FA/token handling.
  - Failed-login and lockout events are recorded; throttling relies on Filament's default login rate limiting.

### Phase 03 - Farm Structure and Master Data (2026-09-29)
- Commit/reference: uncommitted on `dev` (pending review)
- Tests: `vendor/bin/pest` - 59 passed (added `tests/Feature/Farm/FarmMasterDataTest.php`, `tests/Unit/MoneyTest.php`; `userWithRole()` test helper moved to `tests/Pest.php`)
- Package changes: none (added `ext-bcmath` to composer requirements for exact decimal unit conversion).
- What was done:
  - Hierarchy: `farms` (the company) -> `production_units` -> `buildings` -> `rooms` -> `pens`; plus `locations` for non-pen places (stores, cold rooms, silos, quarantine) that Phase 08 inventory locations will use. Models live in `app/Domain/Farm/Models`.
  - Integrity: composite foreign keys keep a pen's room inside the pen's building and a location's building/room inside its production unit/building (verified on MySQL and SQLite); `ValidatesLocationHierarchy` gives the same rule a friendly `DomainException`. All FKs are `restrict`; nothing cascades.
  - Business identifiers: unique `code` per table, normalised to upper case (`HasBusinessCode`); lookup codes are lower snake_case.
  - Master data: breeds, genetic lines, units of measure (base unit + exact `convertTo()` via bcmath), and `lookup_values` - one admin-editable table for the type lists (production unit type, building type, location type, pen purpose, price category; categories in `App\Enums\LookupCategory`).
  - Settings: `farm_settings` rows per farm; the set of settings and their defaults are registered in `SettingDefinitions` (breeding intervals, production targets); `ResolveSettings` reads/writes them typed and validated, falling back to the registered default if a row is missing, and `ensureDefaults()` never overwrites edits. Later phases append definitions there.
  - Prices: `price_lists` + `price_list_items` with `unit_price_minor` (integer); `App\Support\Money` (integer minor units, string parsing/formatting, no floats).
  - Admin UI (Filament): Farm structure group (Farms, Production units, Buildings, Rooms, Pens, Locations), Master data group (Breeds, Genetic lines, Units of measure, Lookup values), Configuration group (Farm settings, Price lists with a Prices relation manager). Adding units/buildings/pens/etc. needs no code.
  - Global search: all farm/master-data resources are searchable by code and name from the panel search box.
  - Permissions: new modules `farm-structure`, `master-data`, `settings` (view/edit only), `price-lists`, with `MasterRecordPolicy` (records referenced elsewhere cannot be deleted; deactivate instead). `RoleSeeder` now holds a default-grant map: new roles get all their defaults, existing roles only receive defaults for *newly created* permissions, so admin edits are never reverted. Settings rows cannot be created or deleted by hand.
  - Audit: `Auditable` applied to every Phase 03 model.
  - `MasterDataSeeder` (idempotent, production-safe): farm "Integrated Princess Azana Farms", 4 production units (Piggery, Feed Mill, Semen Laboratory, Slaughterhouse & Meat Processing), lookups, units, six common breeds, default settings. Included in `DatabaseSeeder`; on a fresh production database run `php artisan db:seed --class=MasterDataSeeder` (after `RoleSeeder`).
- Migration notes: 3 new reversible migrations (farm structure, master data, settings/prices).
- Known issues / notes:
  - The default setting values (114-day gestation, 28-day weaning, etc.) and the seeded breeds/units are conventional starting points; the farm should confirm them in the admin UI.
  - `isInUse()` for pens, locations and genetic lines returns false until Phase 04/08 attach animals and inventory; extend them then so those records become undeletable once referenced (the FKs will already block the delete).
  - Farm-level price lists are a foundation only; item prices are free-text codes until product/inventory master data exists (Phase 08+).
  - The system assumes one active farm (`ResolveSettings` uses the first active farm by default); multi-farm selection is not built.
  - Money assumes 2 minor-unit decimals (NGN kobo).

### Phase 04 - Animal Registry and Lifecycle (2026-09-30)
- Commit/reference: uncommitted on `dev` (pending review)
- Tests: `vendor/bin/pest` - 98 passed (added `tests/Feature/Animal/AnimalDomainTest.php` and `AnimalUiTest.php`)
- Package changes: none. Sonar quality gate on the Phase 03 PR is OK (duplication 0.7%).
- What was done:
  - Registry: `animals` (permanent `animal_number` like `IPA-SOW-0001`, public ULID `public_id`, sex, category, breed, genetic line, birth date (+estimated), source/purchase details, current status/pen/location), `animal_identifiers` (ear tag, RFID, QR, barcode, tattoo, manual), `animal_parentage`, `animal_photos`, plus append-only `animal_status_history`, `animal_movements`, `weight_records`. Models in `app/Domain/Animal/Models`.
  - Actions (`app/Domain/Animal/Actions`, shared by web/API/offline): `RegisterAnimal`, `RecordAnimalMovement`, `ChangeAnimalStatus`, `RecordWeight`, `VoidWeight`, `AddAnimalIdentifier`, `RetireAnimalIdentifier`, `SetAnimalParentage`, `LookupAnimal`, `GetAnimalHistory`. Domain events (dispatched after commit): `AnimalRegistered`, `AnimalMoved`, `AnimalStatusChanged`, `WeightRecorded` (no listeners yet).
  - Numbering: generic `number_sequences` table + `NextNumber` action (row-locked, race-safe). Prefix is the `animals.number_prefix` setting; the sequence is per prefix+category. The number is permanent: it never changes when the category later does (gilt -> sow).
  - Rules: numbers/public ids immutable and animals can never be deleted; identifiers unique across all animals and types, share a namespace with animal numbers, and are never reused (retiring keeps the value reserved); movements go to an active pen (or a location), respect pen capacity, cannot be dated in the future or before the animal's latest movement, and are preserved forever; only active animals can be moved, weighed or get new identifiers; terminal statuses (sold, dead, culled, slaughtered, transferred out) are final, require a reason, and automatically record an exit movement; weights are validated (positive, 2 decimals, <= `animals.max_weight_kg`, not future, not before birth) and corrected by voiding, not editing; parentage enforces sire male / dam female, no self-parent, no ancestor cycles, parents born before offspring, and allows external (unregistered) parent notes.
  - Offline foundation: `idempotency_key` on movements and weights (a retried request returns the original record; reusing a key for another animal is rejected).
  - QR/barcode lookup: `LookupAnimal` resolves a permanent number, public id, QR payload URL or any active identifier; `GET /animals/lookup/{code}` (auth, throttled) returns JSON for scanners/apps and redirects browsers to the animal's profile. `Animal::qrPayload()` is the value to encode in a QR label (rendering the QR image is not built).
  - Admin UI (Animals group): list (status/category/sex/breed/pen filters, position and latest weight columns), register form (identifiers, parentage, first placement) via `RegisterAnimal`, edit form (never number or sex; parentage editable), profile page with passport, current state and a chronological lifecycle history, header actions Move / Record weight / Change status (business-rule errors show as notifications), and relation managers for identifiers, movements (read-only), weights (record / void), status history (read-only) and photos (private disk, image-only, 5 MB, served through the ERP). Global search finds animals by number or any identifier.
  - Permissions: new `animals` module. create = register and record events (move/weigh), edit = change details/identifiers/parentage/void weights, approve = change status (disposal), delete = never. Defaults: General/Farm Manager everything except delete; Breeding Manager view/create/edit/export/print; Veterinarian and Farm Worker view/create; Semen Lab, Slaughter, Sales and Accountant view.
  - Phase 03 follow-ups closed: `Pen`, `Location`, `GeneticLine`, `Breed` and `LookupValue::isInUse()` now count animals and movements, so referenced master data can no longer be deleted.
  - `ImmutableRecord` trait (append-only models) now also backs `AuditLog` and `LoginActivity`.
- Migration notes: 3 new reversible migrations (number sequences, animal registry, animal events).
- Known issues / notes:
  - Setting a terminal status directly needs `animals.approve`; the dedicated flows with approval thresholds, mortality analysis, sale and slaughter arrive in Phases 06, 11, 12 and 15 and will call `ChangeAnimalStatus`. There is no way to reinstate an animal; correcting a wrong status needs the approval workflow (Phase 15).
  - Withdrawal-period blocks on sale/slaughter come with Phase 06 (health). Supplier is free text until Phase 08; semen batch and litter links on parentage are added in Phases 10 and 05.
  - Category/sex pairing (sow/gilt female, boar male) is checked by category code in `RegisterAnimal`; if the farm renames those lookup codes, update the constants there.
  - Editing the birth date after weights or movements exist is not re-validated against them (changes are audited).
  - Bulk/batch (non-individual) records for growers/finishers are not in this phase; individual animals only.

### Phase 05 - Breeding, Farrowing, Litters and Piglets (2026-09-30)
- Commit/reference: uncommitted on `dev` (pending review)
- Tests: `vendor/bin/pest` - 152 passed (added `tests/Feature/Breeding/BreedingDomainTest.php`, `BreedingUiTest.php`, `tests/Unit/RatioTest.php`; shared test helpers `register`, `newPen`, `categoryId`, `boar`, `serve`, `farrow` now live in `tests/Pest.php`). A full service-to-weaning cycle was also verified on MySQL.
- Package changes: none.
- What was done:
  - Schema: `heat_events`, `breeding_services`, `pregnancy_checks`, `farrowings`, `litters`, `piglets`, `litter_losses`, `weaning_records`, and a `litter_id` link on `animal_parentage` (the Phase 04 follow-up). Domain code in `app/Domain/Breeding` and `app/Domain/Litter`.
  - Service: natural or AI; natural needs the boar, AI needs the boar or a free-text semen source (semen batches arrive in Phase 10). Expected pregnancy check, farrowing, weaning, next heat and next service are calculated from the farm settings and **snapshotted on the service**, so later setting changes never rewrite old dates. Rules: active sow/gilt only, not future-dated, not before her latest service, not while confirmed pregnant or with an unweaned litter, minimum age at first service (`breeding.min_first_service_age_days`, only enforced when a birth date is known).
  - Pregnancy: check results (positive/negative) apply to the checked service and to the sow's other open services within the new `breeding.same_heat_window_days` setting (default 3) - double matings cannot be told apart. Abortions close the cycle and are audited with their reason. Sow status (open / served / pregnant / lactating) is derived, never stored (`GetSowStatus`).
  - Farrowing creates its litter automatically (`RecordFarrowing`): counts must add up, the service is auto-matched (confirmed-pregnant nearest to the due date) or given explicitly, the litter gets a permanent number like `IPA-2026-SOW0001-L01` (per-sow sequence), sire comes from the service's boar, and a gilt is promoted to sow at her first farrowing. Farrowings without a recorded service (bought-in pregnant sows) are allowed. Idempotency keys on services and farrowings for offline sync.
  - Piglets: `RegisterLitterPiglets` registers individually tracked piglets as real animals (category piglet) linked to litter, dam and sire, with breed/line from the dam and the birth weight recorded as their first weight; untracked piglets stay as litter counts. Cannot exceed the number born alive.
  - Losses and weaning: `RecordLitterLoss` (append-only, cannot exceed born alive; naming a tracked piglet also marks that animal dead), `WeanLitter` (closes the litter, records count and total weight, moves tracked piglets to the weaner category and optionally into a pen, dates the sow's next service).
  - KPIs (exact decimal strings via `App\Support\Ratio`, half-up rounding): litter - stillborn %, pre-weaning mortality %, weaning %, average birth and weaning weight, gestation and lactation days; sow - parity, average total born / born alive / weaned, mortality %, weaning %, average birth weight, average farrowing interval (`GetLitterKpis`, `GetSowPerformance`).
  - Breeding calendar (`GetBreedingCalendar`): pregnancy checks due, watch-for-return-to-heat, farrowings, weanings and next-service reminders (suppressed once the sow is served).
  - Admin UI (Breeding group): Calendar page, Services (record, view with Pregnancy check / Record farrowing / Record abortion actions), Heat detection, Litters (record farrowing, litter profile with KPIs and Register piglets / Record loss / Wean actions, tracked piglets and losses tabs). A sow's profile shows a Reproduction summary and her litters.
  - Permissions: new `breeding` module. create = record events; edit/approve reserved for corrections in later phases; delete never. Defaults: General/Farm Manager everything except delete; Breeding Manager view/create/edit/export/print; Veterinarian and Farm Worker view/create; Semen Laboratory Manager view.
  - Events (after commit, no listeners yet): `BreedingServiceRecorded`, `PregnancyConfirmed`, `FarrowingRecorded`, `WeaningRecorded`.
  - Records are permanent: services, farrowings, litters, checks, losses and weaning records cannot be edited or deleted (only a service's outcome and a litter's weaning state advance); corrections will go through the Phase 15 approval workflow.
- Migration notes: 2 new reversible migrations (breeding tables; farrowing/litter tables + parentage link).
- Known issues / notes:
  - Double-mated litters take the sire of the service that best matches the farrowing; paternity is uncertain in that case.
  - Cross-fostering, litter transfers and adopted piglets are not modelled; a wrong farrowing count cannot yet be corrected (needs the approval workflow).
  - Pre-weaning losses are recorded on the litter here; Phase 06 mortality analysis should reuse these records for litter-related deaths rather than counting them twice.
  - Farm-wide reproductive KPIs (farrowing rate, non-productive days, dashboards) are Phase 16.
  - Placing piglets in a pen still respects pen capacity; farrowing pens holding a sow plus litter need a capacity that reflects that, or leave piglets unplaced (they are assumed to be with the dam).

### Phase 06 - Health, Veterinary, Mortality, Culling and Biosecurity (2026-10-02)
- Commit/reference: uncommitted on `dev` (pending review)
- Tests: `vendor/bin/pest` - 204 passed (added `tests/Feature/Health/HealthDomainTest.php`, `BiosecurityDomainTest.php`, `HealthUiTest.php`; shared helpers `treat`, `cause`, `lookup`, `medicine`, `vaccine`, `batchOf` in `tests/Pest.php`). The withdrawal sale block and mortality analysis were also verified on MySQL.
- Package changes: none.
- What was done:
  - Schema (3 migrations): diseases, medicines, medicine batches, vaccination schedules, vaccinations, health events, treatments, withdrawal periods, veterinary visits, laboratory results, quarantine records, mortality records, culling records, biosecurity visits, checklist items, checks and check items. Code in `app/Domain/Health` and `app/Domain/Biosecurity`.
  - **Withdrawal / sale guard:** a treatment or vaccination with a withdrawal period (`medicines.default_withdrawal_days`, snapshotted on the record; a vet may lengthen but never shorten it) starts a withdrawal period automatically. `AssertAnimalCanEnterFoodChain` blocks selling or slaughtering any animal under an active withdrawal (until the end date) or in quarantine/isolation. It is enforced inside `ChangeAnimalStatus` for Sold and Slaughtered, so the sales and slaughter phases inherit it, and in culling when the disposal is a sale or slaughter. Early clearing needs a reason, `health.approve`, and is audited. Dead, culled (destroyed) and transferred-out animals are not blocked.
  - Health records: `ReportHealthEvent` / `ResolveHealthEvent`, `RecordTreatment`, `RecordVaccination` (with or without a schedule; schedule category and vaccine type are checked), `RecordVeterinaryVisit`, `RecordLabResult`, `StartQuarantine` (optionally moves the animal) / `ReleaseQuarantine`. Medicine batches are checked for expiry, activity and matching medicine. All are append-only except a case's resolution, a quarantine's release and a withdrawal's clearance. Idempotency keys on treatments and vaccinations.
  - **Vaccination reminders:** `GetVaccinationsDue` works from active schedules (first dose at an age after birth, boosters at an interval after the last dose, single-dose schedules finish), for active animals with a known birth date, within `health.vaccination_reminder_days`.
  - **Mortality:** `RecordMortality` snapshots pen, litter, sow, breed, age, production stage, last weight and cause at death, marks the animal dead and records the exit; a tracked piglet of a suckling litter also gets its pre-weaning loss entry. `RecordLitterLoss` now goes through `RecordMortality` for named animals, so every animal death has exactly one mortality record and one loss entry. `GetMortalityAnalysis` groups deaths by pen, stage, age band, litter, sow, breed, cause or month and includes untracked piglet losses (counts on a litter) so nothing is missed or double counted. Age bands are the `health.mortality_age_band_limits` setting.
  - **Culling:** `RecordCulling` requires reason, weight (also recorded as a weight), health status, disposal type and disposal/sale value, and stores a production-performance snapshot (a sow's parity and litter averages); creating one needs `health.approve` (disposal).
  - Health alerts (`GetHealthAlerts`): overdue/due vaccinations, expired/expiring batches, cases open too long, long quarantines, vet follow-ups due.
  - **Biosecurity:** visitor log (`RecordVisitorArrival` / `RecordVisitorDeparture`) - a visitor without a health declaration, or with less pig-free time than `biosecurity.min_pig_contact_free_hours` (48), can only be admitted with the approval of someone holding `biosecurity.approve`; records are retained and cannot be edited or deleted. Inspections (`RecordBiosecurityCheck`) score a configurable checklist and snapshot the wording answered.
  - Admin UI: Health group (alerts, vaccinations due, withdrawal periods, cases, quarantine, mortality, mortality analysis, culling, treatments, vaccinations, vet visits, lab results, medicines with batches, vaccination schedules, diseases) and Biosecurity group (visitor log, inspections, checklist). The animal profile has a Health section (withdrawal, quarantine, open cases, last treatment/vaccination) and a Health menu (treat, vaccinate, report sick/injured, quarantine, record death, cull); "Change status" no longer offers dead/culled.
  - Permissions: new `health` and `biosecurity` modules (nothing deletable; master data only while unreferenced). Veterinarian, General and Farm Manager: everything except delete; Breeding Manager, Farm Worker: view/create; Sales Officer, Slaughter Manager: view health (to see withdrawals); Store Officer / Semen Lab: visitor log.
  - Domain event `MortalityRecorded`. `ImmutableRecord` backs all the append-only records.
- Migration notes: 3 new reversible migrations. New lookup categories seeded (medicine type, cause of death, culling reason) and 6 new farm settings.
- Known issues / notes:
  - Medicine batches record identity and expiry only; stock quantities, and the deduction of medicine used, join the inventory ledger in Phase 08.
  - Mortality "batch" analysis needs grower/finisher batches (Phase 07); it can be added as another dimension then.
  - A newly created vaccination schedule makes every matching animal that never had it overdue immediately; enter historic vaccinations (or start schedules on the right category) to avoid a flood of reminders.
  - Culling a sow that still has an unweaned litter is not blocked; the litter would need weaning or moving separately.
  - Alerts are shown on a page; sending them as notifications and creating tasks is Phase 15. Mortality corrections and reinstating an animal need the approval workflow (Phase 15).
  - Lab results are text only (no file attachments yet).

## In Progress
None

## Blocked
None

## Rules
Update this file after every completed phase.

Record:
- phase
- date
- commit/reference
- tests
- known issues
- migration notes
- follow-up work
