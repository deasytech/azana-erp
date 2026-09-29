# Implementation Status

## Current Phase
Phase 03 - Farm Structure and Master Data (not started)

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
