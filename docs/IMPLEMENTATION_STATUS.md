# Implementation Status

## Current Phase
Phase 14 - Finance, Costing, Cash Flow, Budgets and Profitability (not started)

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

### Phase 07 - Grower/Finisher Management and Feed Consumption (2026-10-03)
- Delivery: two stacked PRs - domain (`phase-07-domain`, #10) and UI, tests and docs (`phase-07-ui`).
- Tests: `vendor/bin/pest` - 256 passed (added `tests/Feature/Production/ProductionDomainTest.php` and `ProductionUiTest.php`; batch helpers `openBatch`, `feed`, `weighIn`, `scenario` in `tests/Pest.php`). The worked scenario was also verified on MySQL.
- Package changes: none.
- What was done:
  - Schema: `feed_types`, `production_batches`, `production_batch_events` (append-only head-count ledger), `production_batch_animals`, `batch_weigh_ins`, `feed_consumption_records`, `production_costs`. Code in `app/Domain/Production` and `app/Domain/Feed`.
  - **Batches:** a grower/finisher group with a code like `BATCH-2026-001`, a stage, optional breed/pen/target weight and a first placement (and first weigh-in if the placement weight is given). Head count is the sum of the ledger and can never go negative - not today, and not on a back-dated event's own date. A batch closes itself when its last pig leaves; closed batches accept no events.
  - **Mortality integration:** `ChangeAnimalStatus` now takes a tracked animal out of its batch (death -> mortality, sold, slaughtered, culled, transferred out), reducing the count. Untracked deaths go through `RecordBatchMortality` (with a cause) and appear in the mortality analysis (by stage, pen, breed, cause, age from the batch's placement age); tracked deaths are counted once, from their mortality record.
  - **Weigh-ins:** average weight of a sample, validated (positive, plausible, between batch start and today, sample no larger than the pigs present that day, one valid weigh-in per day); wrong ones are voided, never edited.
  - **Feed:** `RecordFeedConsumption` for a batch or a single animal; cost (quantity x cost per kg) snapshotted in whole minor units, rounded half up; voidable; idempotency keys. **It does not post stock** - the inventory ledger is Phase 08, and real feed cost arrives with Phase 09's feed batches.
  - **Costs:** other costs (medicine, labour, utilities, transport, other) attributed to a batch and voidable.
  - **Calculations (`GetBatchPerformance`), from valid (non-voided) records only:** ADG = (end average weight - start average weight) / days between weigh-ins; gain = that difference x pigs alive on the end weigh-in date; FCR = feed eaten after the start weigh-in up to the end weigh-in / gain (feed eaten by pigs that later died stays in); cost per pig = (entry + feed + other costs) / pigs that did not die; cost per kg gained = (feed + other costs) / gain; expected market date = latest weigh-in date + days to reach the target weight at the measured ADG, rounded up with exact arithmetic (target: the batch's own or `production.target_market_weight_kg`, default 100 kg). The period can be any two valid weigh-in dates. `GetAnimalGrowth` does the same for a tracked animal from its weight records, and `GetProductionSummary` lists every active batch.
  - Admin UI (Production group): Overview (active batches side by side), Batches (start batch form; batch page with performance and cost sections, a "Record" menu - add pigs, remove pigs, deaths, weigh-in, feed, cost, add a tracked animal, adjust count - and a "Choose period" action; tabs for the head-count ledger, weigh-ins, feed, other costs and tracked animals, with voiding), Feed records (for a batch or an animal, voidable) and Feed types. A tracked animal's profile shows its batch, ADG, gain and FCR.
  - Permissions: new `production` module (nothing deletable; feed types only while unused). Farm/General Manager everything except delete, Farm Worker view/create (daily entry), Accountant view/export (costs), Feed Mill, Store, Sales, Slaughter, Breeding and Veterinarian view. Count adjustments need `approve`; voiding needs `edit`.
- Migration notes: 2 new reversible migrations. One new farm setting (target market weight) and starter feed types (pre-starter, starter, grower, finisher, sow gestation/lactation, boar).
- Known issues / notes:
  - Treatments and withdrawal periods (Phase 06) are per animal; group treatments for untracked batch pigs are not modelled, so selling untracked pigs from a batch is not checked against withdrawals. Track treated animals individually, or add batch-level treatments later.
  - Feed cost per kg is typed in for now; Phase 09 derives it from feed batches and Phase 08 posts the stock movement.
  - Money is shown in the first farm's currency (`Farm::defaultCurrency()`); older screens still assume NGN.
  - "Cost per pig" and "cost per kg gained" use the definitions above (written into the `GetBatchPerformance` comments); a finance view (Phase 14) may add others.
  - Sales and slaughter of untracked pigs (Phases 11-12) should call `RemovePigsFromBatch`; tracked animals already flow through `ChangeAnimalStatus`.

### Phase 08 - Inventory Transaction Engine and Procurement (2026-10-05)
- Delivery: four stacked PRs - inventory ledger (#14), procurement (#17), inventory UI (#18) and procurement UI with docs (`phase-08-procurement-ui`).
- Tests: `vendor/bin/pest` - 351 passed (added `tests/Feature/Inventory/{InventoryLedgerTest,StockControlTest,InventoryUiTest}.php`, `tests/Feature/Procurement/{ProcurementTest,ProcurementUiTest}.php`; helpers `stockItem`, `store`, `receiveStock`, `issueStock`, `supplier`, `purchaseOrder`, `receiveGoods` in `tests/Pest.php`).
- Package changes: none.
- What was done:
  - Schema (3 migrations): suppliers, inventory locations (stores), items, batches; the ledger (`inventory_transactions`), cost layers, stock counts and lines, stock adjustments; purchase requests/orders and lines, goods receipts and lines, supplier invoices and payments. Code in `app/Domain/Inventory`, `app/Domain/Supplier` and `app/Domain/Procurement`.
  - **Ledger:** `PostInventoryTransaction` is the only way stock changes - one signed line per item, store and batch, never edited or deleted, stock can never go negative, idempotency keys supported. Types: opening, purchase, receipt, production, consumption, sale, transfer in/out, return, wastage, adjustment. Mistakes are undone by `ReverseInventoryTransaction` (an opposite line); a receipt can be reversed only while all of it is still held, an issue comes back at the value it left with. Domain event `InventoryTransactionPosted`.
  - **Valuation:** each receipt keeps a cost layer; issues use FIFO (default) or weighted average, set by `inventory.valuation_method`. Values are whole minor units and the last unit taken from a layer takes all its value, so no cost is lost to rounding. Balances read from the layers, which tests check always agree with the ledger.
  - **Batches:** items can be tracked by batch and expiry. Receiving creates the batch (supplier, expiry). Issuing without naming a batch uses the one closest to expiry first (`IssueStock`); expired stock cannot be received, consumed, sold or transferred but can be written off or corrected. Batches can be blocked (recall).
  - `ReceiveStock`, `IssueStock`, `TransferStock` (both legs, at the cost it left with); `GetStockLevels`; `GetReorderAlerts` (item `reorder_level`); `GetExpiryAlerts` (`inventory.expiry_warning_days`).
  - **Counts and adjustments:** a count of a store is started (system quantities snapshotted), counted line by line, submitted (every line counted, every difference explained) and approved by someone holding `inventory.approve` - by default not the person who submitted it (`inventory.require_separate_approver`) - which posts each variance as an adjustment. Manual adjustments follow the same approve/reject path. Wastage posts immediately with a reason (it is not an adjustment).
  - **Feed from stock:** `RecordFeedConsumption` can name a store; the feed is then taken out of stock through the ledger, its cost comes from the ledger, and voiding the feed record reverses the stock (linked by `feed_consumption_records.inventory_group`). A feed type is linked to its stock item on the item.
  - **Procurement:** purchase requests (draft, submitted, approved, rejected, ordered, cancelled); purchase orders from a request or directly, with the supplier's terms and the currency copied on, needing approval unless within `procurement.po_approval_threshold_minor` (default 0 = always); goods receipts post to the ledger at the ordered cost (batch, expiry and supplier kept), may not exceed the order plus `procurement.over_receipt_tolerance_percent`, and can be voided (reversing the stock) unless the goods were used or invoiced; supplier invoices matched to the value received and not yet invoiced (tax on top, due date from the order's terms); payments in parts, never above what is owed, with a payment above `procurement.payment_approval_threshold_minor` held for approval (it still reserves its amount). `GetPurchaseTrace` follows an order from request to payment; `GetSupplierBalances` shows owed and overdue.
  - Admin UI - **Inventory** group: stock on hand (receive / use or write off / transfer), stock alerts, stock ledger (filters, reverse - which undoes everything posted with the line, so both sides of a transfer), stock counts, adjustments, items, stores, batches. **Purchasing** group: supplier balances, suppliers, purchase requests, purchase orders (approve, receive goods, record invoice; tabs for items, receipts, invoices and payments), goods receipts, supplier invoices, supplier payments. Feed records have a "Take from store" field.
  - Permissions: new `inventory` and `procurement` modules (nothing deletable; items, stores, batches and suppliers only while unreferenced). General and Farm Manager everything except delete; Store Officer and Feed Mill Manager operate (no approve); Accountant purchasing without approve; Farm Worker may view/receive stock. The shared approval gate is `App\Domain\System\Actions\AssertMayDecide`.
- Migration notes: 3 new reversible migrations. Starter stores (main, feed, veterinary) and 7 new farm settings (valuation method, expiry warning, separate approver for inventory and for purchasing, PO and payment approval limits, over-receipt tolerance).
- Known issues / notes:
  - Medicine and vaccine stock is not yet linked to treatments and vaccinations (a medicine's batch still holds identity and expiry only); deducting medicine used from stock is follow-up work.
  - Weighted average is computed per item, store and batch pool; switching the valuation method later is safe (layers serve both).
  - Counts adjust by the variance found at count time; stock used since the count must still cover a shortfall or the approval is refused.
  - Purchase requests and orders cannot be edited once created (cancel and recreate). There is no "close order" for a part-delivered order that will not be completed.
  - Payments record the money out only; posting to the finance ledger is Phase 14. Money is shown in the first farm's currency.
  - Lines owned by a goods receipt or a feed record cannot be reversed from the ledger screen; void the document instead.
  - Phase 09 production posts to the ledger as `production` (finished feed) and `consumption` (raw materials) through the same actions.

### Phase 09 - Feed Formulation and Feed Mill (2026-10-05)
- Delivery: two stacked PRs - domain (#22) and UI, tests and docs (`phase-09-ui`).
- Tests: `vendor/bin/pest` - 390 passed (added `tests/Feature/Feed/FeedMillTest.php` and `FeedMillUiTest.php`; helpers `millFixture`, `plannedFeedOrder`, `completedFeedRun`, `approvedRequest` in `tests/Pest.php`). The worked run below was calculated by hand and is asserted in the tests.
- Package changes: none.
- What was done:
  - Schema (1 migration): `feed_formulas`, `feed_formula_items`, `feed_production_orders`, `feed_production_order_lines`, `feed_production_batches`. Code in `app/Domain/Feed`. Feed types already existed (Phase 07).
  - **Formulas:** a recipe for one feed type - ingredients are stock items given as a percentage of the mix, an expected process loss, and an optional nutritional specification (crude protein, fibre, fat, calcium, phosphorus, lysine, energy; targets typed in by the nutritionist, not calculated). A draft can be saved while the ingredients do not yet add up to 100%; activation needs exactly 100%, active ingredients and a stock item for the finished feed (a stock item linked to the feed type). Active formulas never change: a new version is copied into a draft, and activating it retires the previous active version of that code. New versions are serialized by locking the code's rows.
  - **Cost:** `GetFormulaCost` prices a formula at today's ingredient costs (what is on hand, else the last receipt): per kg of finished feed (allowing for process loss), per bag (`feed.bag_weight_kg`, default 25) and per tonne, and names ingredients with no cost yet.
  - **Production orders:** `FM-000123`. Planned from an active formula for an amount of finished feed, a source store and an output store; the materials needed (output / (1 - loss), times each inclusion, in each ingredient's own unit) are calculated and kept on the order, so later formula changes never alter it. The order shows what the source store lacks. Production staff confirm what was actually used (or use the planned quantities) before completing.
  - **Completion** (`CompleteFeedProduction`, all or nothing in one transaction): the confirmed materials are taken out of the source store through the inventory ledger (valued by it, batches closest to expiry first), the finished feed is received into the output store as a new batch (order number or a chosen batch number, expiry from `feed.finished_feed_shelf_life_days` if the item expires) at the production cost, and the cost is worked out: materials + other costs, per kg of feed actually made. All ledger lines of the run share one group id with the order as their source, so a feed record -> finished batch -> run -> raw-material batch -> supplier is traceable. Domain event `FeedProductionCompleted`.
  - Worked run (tested): 1,000 kg at 2% loss from 60/30/10 maize/soya/premix needs 612.245 / 306.122 / 102.041 kg; with maize 350.00, soya 900.00 and premix 2,000.00 per kg the materials cost 693,877.55, other costs 10,000.00, total 703,877.55 over 990 kg made = 710.99 per kg; a 100 kg feed record taken from that batch costs 71,098.74 (rounded to whole minor units).
  - A planned order can be cancelled; a completed run can be reversed (the finished feed leaves stock, the materials return at the cost they left with), refused when any of the finished feed has been used.
  - Admin UI (Feed mill group): Formulas (create/edit drafts with an ingredient list and running total; view with nutrition, cost per kg/bag/tonne and ingredient table; activate, retire, new version), Production orders (create; view with materials against stock, confirm quantities, use planned, complete, cancel, reverse) and Finished batches (cost per kg of every run).
  - Permissions: new `feed-mill` module and a new **Nutritionist** role (formulas: view/create/edit, no approve). Feed Mill Manager, General and Farm Manager have everything except delete; Store Officer, Accountant and Sales Officer can view. Reversing a run needs `feed-mill.approve`. Formulas are deletable only while an unused draft.
- Migration notes: 1 new reversible migration, 2 new farm settings (bag weight, finished-feed shelf life), the Nutritionist role (existing databases get it on the next role seed).
- Known issues / notes:
  - Activating a formula needs `feed-mill.edit`, so a nutritionist can activate their own formulas; make activation approval-only if a second pair of eyes is wanted.
  - Nutritional values are targets; there is no ingredient analysis table to calculate them from yet.
  - Other production costs (labour, power, bags) are one amount typed at completion; Phase 14 costing may break them down.
  - A production run uses one source store and one output store; multi-store mixing is not modelled.
  - Formula cost uses the current ingredient cost, which can differ from what a run later costs under FIFO; the finished batch always carries the real ledger cost.

### Phase 10 - Semen Production, Laboratory QC and Semen Inventory (2026-10-06)
- Delivery: two stacked PRs - domain (#27) and UI, tests and docs (`phase-10-ui`).
- Tests: `vendor/bin/pest` - 444 passed (added `tests/Feature/Semen/SemenDomainTest.php` and `SemenUiTest.php`; helpers `semenBoar`, `collectSemen`, `passSemenQc`, `processedSemen`, `releasedSemen` in `tests/Pest.php`).
- Package changes: none.
- What was done:
  - Schema (1 migration): `semen_boars`, `semen_collections`, `semen_batches`, `semen_qc_records`, plus `inventory_items.breed_id` (one semen stock item per breed), `breeding_services.semen_batch_id` and `price_list_items.inventory_item_id`. Code in `app/Domain/Semen`.
  - **Boar programme** (`ManageSemenBoar`): an active boar is enrolled as active, resting or retired, with an optional rest period and weekly target of its own.
  - **Collections** (`RecordSemenCollection`): from an active, collecting, non-quarantined boar that has rested (`semen.min_collection_interval_days`, default 4, or the boar's own); volume 0.1-1000 ml; idempotency key supported. **Every collection creates its batch at once**, numbered like `IPA-SM-DUR-20260904-001` (prefix, breed code, date, daily sequence), expiring after `semen.shelf_life_days` (default 4), status pending QC. Event `SemenBatchCollected`.
  - **Laboratory QC** (`RecordSemenQc`): motility, concentration and abnormal forms are judged against the farm's standards (`semen.min_motility_percent` 70, `semen.min_concentration_million_per_ml` 200, `semen.max_abnormal_percent` 20): a batch that meets them is passed, one that does not is failed and the reasons are kept. QC is recorded once and never edited.
  - **Processing** (`ProcessSemenBatch`): the doses made cannot exceed what the ejaculate yields - volume x concentration x motility / `semen.sperm_per_dose_million` (default 2,500 million). Example: 250 ml x 300 million/ml x 80% = 60,000 million -> at most 24 doses.
  - **Release** (`ReleaseSemenBatch`): a processed, passed batch is released by someone holding `semen.approve` and (by default, `semen.require_separate_approver`) not the analyst who recorded the QC. Its doses are received into the breed's semen stock item through the inventory ledger, by batch and expiry, valued at `semen.cost_per_dose_minor` (default 0). Event `SemenBatchReleased`.
  - **Saleability:** `AssertSemenBatchSellable` is the single rule - released, not expired, not blocked. A failed batch never reaches stock, so it can never be sold; a quarantined batch's stock is blocked in the inventory ledger. Phase 11 sales and AI both go through it.
  - **Quarantine, destruction, expiry** (`ManageSemenBatch`, `ExpireSemenBatches`): a batch can be quarantined (stock blocked) and cleared with approval, or destroyed with a reason; destroying or expiring writes the doses left off the ledger as wastage. A daily scheduled job expires batches past their date.
  - **Artificial insemination:** `RecordService` accepts a released batch: AI only, one or more doses taken from a named store through the ledger (source: the service), the batch's boar recorded as sire even if that boar has left the farm.
  - **Reports:** `GetSemenProduction` (collections, passed/failed by the lab's decision, doses and attainment against weekly targets - a boar's own, else `semen.target_doses_per_week`, default 40 - for any period), `GetSemenStock` (doses by breed, boar, batch, expiry, store and sellability), `GetSemenPrice` (dose price from the active price lists in force, via the price list item's stock item).
  - Admin UI (Semen group): Semen stock, Batches (record a collection; batch page with collection, QC, price per dose, and Record QC / Record doses made / Release / Quarantine / Clear quarantine / Destroy steps), Production report (period checked on the server), Boars. Price list items can be linked to a stock item; the breeding service form can pick a released batch and the store the dose comes from.
  - Permissions: new `semen` module. Semen Laboratory Manager, General and Farm Manager have everything except delete (release and destroy need approve); Breeding Manager and Farm Worker view/create; Veterinarian, Store Officer and Sales Officer view; Accountant view/export. Nothing is deletable, not even by the Owner.
- Migration notes: 1 new reversible migration (verified up/down/up on SQLite and MySQL), 9 new farm settings, a semen laboratory store and one semen stock item per breed (`SEMEN-<BREED>`) from the master data seeder.
- Known issues / notes:
  - A failed batch cannot be retested; it can only be destroyed.
  - Released doses carry no stock value until `semen.cost_per_dose_minor` is set.
  - Sales (Phase 11) must call `AssertSemenBatchSellable` and issue doses of the batch's inventory batch; nothing else sells semen yet.
  - The doses in a batch are one inventory batch; splitting a batch across stores is done with an ordinary stock transfer.
  - Boar fertility trends and breed-level production targets beyond the weekly dose target are Phase 16 reporting.

### Phase 11 - Customers, Semen Sales and Pig Sales (2026-10-07)
- Delivery: one PR for the whole phase (under 100 files), committed in two steps - domain, then UI and docs.
- Tests: `vendor/bin/pest` - 495 passed (added `tests/Feature/Sales/SalesDomainTest.php` and `SalesUiTest.php`; helpers `customer`, `creditCustomer`, `semenOrder`, `confirmed`, `dispatched`, `pay` in `tests/Pest.php`). A worked sale is asserted: 10 semen doses at 15,000.00 with a 10% discount (135,000.00) plus a tracked pig of 110.5 kg at 1,200.00 per kg (132,600.00) = 267,600.00.
- Package changes: none.
- What was done:
  - Schema (1 migration): `customers`, `sales_orders`, `sales_order_lines`, `stock_reservations`, `invoices`, `invoice_lines`, `payments`, `payment_allocations`. Code in `app/Domain/Sales`. Semen and pig sales are order-line kinds (semen / tracked pig / pigs from a batch) rather than separate tables, so one order can mix them.
  - **Customers** (`SaveCustomer`): numbered `C-000001`, with a customer type (new lookup category: farmer, breeder, butcher, retailer, institution, individual). A customer starts on cash terms: credit is set separately.
  - **Credit** (`SetCustomerCredit`): a limit, payment terms and a status (none, approved, on hold, blocked), set by someone holding `sales.approve` and, by default, not whoever set the customer up (`sales.require_separate_approver`). Only an approved limit counts; on hold means a limit of zero.
  - **Orders** (`CreateSalesOrder`): every line copies its price (and the discount) onto itself, so later price-list changes never alter an order or an invoice; semen defaults to the price list's price for the breed. Idempotency key supported.
  - **Credit rule** (`CheckCustomerCredit`, setting `sales.credit_enforcement`): what the customer would owe after the order (outstanding + this order - any deposit) must stay within the approved limit. "block" refuses, "warn" confirms and keeps the warning on the order, "off" does not check. A blocked customer is refused; with `sales.block_credit_when_overdue` (on) a customer with overdue invoices gets no new credit. A customer with no credit buys by paying in advance (a deposit).
  - **Confirm and reserve** (`ConfirmSalesOrder`): a discount above `sales.discount_approval_threshold_percent` (default 10) needs someone who can approve. Semen doses are reserved by batch and store (what others have reserved is not available), tracked pigs are checked for withdrawal periods and quarantine and cannot be reserved twice, pigs from a batch are reserved against its head count. Cancelling releases the reservations.
  - **Dispatch** (`DispatchSalesOrder`, all or nothing): semen leaves stock through the inventory ledger by batch (source: the order), tracked pigs become sold (the Phase 06 withdrawal guard applies again), batch pigs come off its head count, and the invoice is issued (`INV-000001`, due after the customer's terms) from the order's lines with the traceability kept: invoice line -> semen batch -> boar -> collection, or -> animal / production batch. Any deposit the customer holds is applied to it. Semen is re-checked with `AssertSemenBatchSellable`, so a failed, quarantined, expired or blocked batch can never be sold. Event `SaleConfirmed`.
  - **Payments** (`RecordCustomerPayment`): cash, bank transfer or POS, `RCT-000001`. A payment settles the invoices named, or the oldest first; a part payment settles part; whatever is left stays with the customer as a **deposit** and is applied to their next invoice (or by hand, `AllocateCustomerFunds`). A payment can be voided (the invoices owe again). Event `PaymentReceived`. Invoices and allocations are immutable.
  - **Balances and history:** `GetCustomerAccount` (owes, overdue, deposit, credit headroom), `GetOutstandingBalances`, `GetCustomerHistory` (orders, invoices and payments, newest first).
  - Admin UI (Sales group): Outstanding balances, Orders (line form for semen / pigs; page with confirm, dispatch and invoice, cancel; lines with reservations), Invoices (lines traced to batch/boar/animal, payments applied), Payments received (apply to oldest, chosen invoices or keep as deposit; prefilled from a customer or invoice; void), Customers (account summary, Set credit, Receive payment; orders, invoices and payments tabs).
  - Permissions: new `sales` module. General and Farm Manager everything except delete (approving credit and large discounts); Sales Officer and Accountant view/create/edit/export/print; Store Officer, Semen Laboratory Manager view. Customers are deletable only before they have sales history; nothing else is deletable.
- Migration notes: 1 new reversible migration; 4 new farm settings (credit rule, no credit while overdue, discount approval limit, separate credit approver); new customer-type lookup values.
- Known issues / notes:
  - An invoice cannot be voided or edited: a sold tracked pig cannot be reinstated until the Phase 15 approval workflow, so corrections need credit notes, which come with finance (Phase 14).
  - Sales of untracked pigs from a batch do not check withdrawal periods (treatments are per animal; Phase 07 note still applies).
  - Prices for pigs are typed on each line; only semen has a price list entry to default from.
  - Refunds of deposits and customer statements are not built; the finance phase owns them. Receipts post no accounting journal yet (Phase 14).
  - Meat sales (Phase 13) will use the same order, invoice and payment documents with a new line kind.

### Phase 12 - Slaughter, Carcass, Meat Processing and Meat Inventory (2026-10-08)
- Delivery: one PR for the whole phase (under 100 files), committed in two steps - domain, then UI and docs.
- Tests: `vendor/bin/pest` - 536 passed (added `tests/Feature/Slaughter/SlaughterDomainTest.php` and `SlaughterUiTest.php`; helpers `slaughterDay`, `receivePig`, `slaughterPig`, `meatLines`, `makeMeat` in `tests/Pest.php`). A worked run is asserted: a pig of 100 kg live with a 76 kg carcass dresses at 76.00%; its 20,000.00 cost to raise plus 1,000.00 of processing is shared by weight over 67 kg of products (leg 20, loin 15, shoulder 18, belly 12, liver 2) = leg 6,268.66, loin 4,701.49, shoulder 5,641.79, belly 3,761.19, liver 626.87 (total 21,000.00).
- Package changes: none.
- What was done:
  - Schema (1 migration): `meat_products`, `slaughter_batches`, `slaughter_records`, `carcasses`, `carcass_adjustments`, `meat_production_batches`, `meat_production_lines`. Code in `app/Domain/Slaughter` and `app/Domain/Meat`.
  - **Slaughter days** (`ManageSlaughterBatch`): `SB-000001`, scheduled, in progress while pigs are received, closed once nothing is left waiting; cancelling is allowed until a pig is slaughtered and sends received pigs back.
  - **Intake and inspection** (`RecordSlaughterIntake`): a tracked animal or a group of pigs from a production batch is received with its live weight and an ante-mortem inspection. A pig that fails (notes required) is rejected and goes no further. **Withdrawal periods and quarantine are enforced** (and again when the pig is slaughtered); a pig reserved for a customer's order, already received, inactive or over the plausible weight is refused; batch groups cannot exceed the pigs free (head count less customer reservations and pigs already waiting). The live weight is also recorded as the animal's weight. The cost of raising the pig is taken from the farm's records (`GetLiveCost`: feed recorded against the animal plus, for a batch member or group, the batch's cost per pig) unless typed in.
  - **Slaughter and carcass** (`RecordSlaughter`): hot carcass weight (no more than the live weight), post-mortem inspection (fit, partly condemned with the weight condemned, or condemned). The carcass is numbered `CAR-000001` and its **dressing percentage = carcass weight / live weight x 100** is stored; the animal is marked slaughtered (or the pigs come off their batch). A fully condemned carcass is a recorded loss and makes no meat. Event `SlaughterCompleted`.
  - **Weight corrections** (`AdjustCarcassWeight`): a "slaughter adjustment" - needs `slaughter.approve` and, by default, someone other than who recorded the slaughter (`slaughter.require_separate_approver`); only before the carcass is processed; kept with old weight, new weight, reason and approver.
  - **Meat production** (`ProduceMeat`, all or nothing): carcasses hanging in the chiller are made into products from the **product catalogue** (10 starter products seeded: whole carcass, leg, loin, shoulder, belly, ribs, liver, head, trotters, fat; each is its own stock item in kg). What is made plus waste cannot weigh more than the carcasses' usable weight (hot weight less condemned). The cost of raising the pigs plus processing costs is **attributed to the products by weight** (the last takes the rounding remainder so nothing is lost), and each product **enters inventory through a ledger transaction** in a cold room, in a batch numbered like `IPA-MT-20261008-001` with a use-by date (production date + the product's shelf life). Event `MeatProduced`. A run can be reversed (the meat leaves stock, the carcasses hang again), refused once any has been sold or used.
  - **Traceability:** meat stock batch -> meat production batch -> carcass -> slaughter record -> animal or production batch (`GetMeatTrace`); every ledger line carries the meat batch as its source. Meat stock by product, batch, use-by and cost: `GetMeatStock`. Yield: `GetSlaughterYield` (dressing against `production.target_dressing_percent`, losses, carcasses below `slaughter.min_dressing_percent_alert`, per slaughter day). Use-by dates feed the inventory expiry alerts.
  - Admin UI (Slaughter & meat group): Meat stock, Slaughter days (schedule; receive a pig or group; record slaughter per pig; close or cancel), Carcasses (yield, correct weight), Meat production (make meat from chosen carcasses; products, cost, traced-to tab; reverse), Yield report (period checked on the server), Product catalogue.
  - Permissions: new `slaughter` module. Slaughter Manager, General and Farm Manager have everything except delete (corrections and reversals need approve); Veterinarian view/create/edit (inspections); Store Officer, Sales Officer, Accountant view. Nothing is deletable; a catalogue product only while no meat has been made from it.
- Migration notes: 1 new reversible migration (verified up/down/up and seeded on MySQL), 2 new farm settings, a cold room store and 10 starter products with their stock items from the master data seeder.
- Known issues / notes:
  - Withdrawal periods are per animal, so pigs received from a batch as an untracked group are not checked against them (Phase 07 note still applies); track treated pigs individually.
  - By-products (head, trotters, fat) are stocked and costed like any product; waste has no cost or stock. A condemned carcass's cost is a loss, not carried onto meat.
  - Carcass chilling shrink is not modelled (the hot weight is used throughout).
  - Phase 13 sells meat from these cold-room batches using the Phase 11 order, invoice and payment documents with a new line kind.

### Phase 13 - Meat Sales and End-to-End Traceability (2026-10-09)
- Delivery: one PR, one commit (under 100 files).
- Tests: `vendor/bin/pest` - 562 passed (added `tests/Feature/Traceability/MeatSalesTest.php`, `TraceProductTest.php` and `TraceUiTest.php`; helpers `traceChain` and `twoLotsOfLeg` in `tests/Pest.php`).
- Package changes: none.
- What was done:
  - Schema (1 migration): `meat_production_line_id` on `sales_order_lines` and `invoice_lines` - a meat line points at the lot it takes meat from (one product within one meat production batch).
  - **Meat sales** reuse the Phase 11 order, invoice and payment documents with a new line kind, "Meat", sold by weight from a cold room. A line names a product and the system **picks the lots** (`PickMeat`): earliest use-by first, across as many lots as it takes, counting only what is free (on hand less what other orders hold) and not past its use-by date - or it names one lot. One entered line can become several order lines, one per lot. The price defaults to the product's price-list price (`GetItemPrice`, now shared with semen).
  - **Reservation and dispatch:** confirming reserves the kilograms against the lot (the stock row is locked while counting, in the same fixed order as semen and pigs); meat past its use-by date, from a reversed batch or from a blocked lot is refused at confirmation and again at dispatch. Dispatch takes the meat out of the cold room through the inventory ledger (`Sale`, source: the order, in the lot's batch) and the invoice line keeps the lot.
  - **Picking list** (`GetPickingList`): for every line of a confirmed order - item, where to take it from, batch, use-by date and quantity (semen doses, meat lots, pigs with their pen, batch pigs).
  - **Trace this product** (`TraceProduct`): for a meat batch, a pig or a semen batch, everything that led to it and everything that came of it. Meat batch -> carcass -> slaughter day -> pig -> its pens, production batch, feed (feed type, the finished feed batch it came from, the raw material batches that went into it and their suppliers) and its litter, sow, boar and semen batch; downstream, the invoice and customer. A pig traces to the meat made from it and the customers who bought it (or the customer it was sold to alive); a semen batch traces to its boar, the sows it served and their litters, and the customers who bought it. Slaughtered groups trace through their production batch. The result is stages (suppliers -> raw materials -> feed -> genetics -> litter -> pig -> housing -> slaughter -> meat -> sales -> customers), links between nodes, and which nodes are before and after the subject.
  - Admin UI: **Trace a product** page (choose meat batch / animal / semen batch, enter the number; every node links to its page; people without sales rights see the trace without invoices and customers), "Trace this product" buttons on meat batches, animals and semen batches, meat in the order form, a Picking list on confirmed orders, the meat lot on invoice lines, and an **Items bought** tab on a customer (customer history, line by line with the batch it came from).
  - Permissions: no new module. The explorer needs view rights on animals and slaughter; sales stages additionally need sales view.
- Migration notes: 1 new reversible migration; no new settings.
- Known issues / notes:
  - The trace reaches the feed a pig ate only where feed was taken from a store (the record then knows its finished feed batch and so its raw materials); feed recorded without a store shows only its feed type.
  - Pigs slaughtered as an untracked group trace to their production batch, not to individual parents.
  - A pig's treatments and vaccinations are not part of the trace graph yet.
  - Auto-picking happens when the order is drafted; if the stock changes before confirmation, confirmation refuses and the order must be redrafted.

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
