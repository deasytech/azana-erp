# Phase 18 - Public Website and Customer-Facing Surface

## Objective
Build www.azanafarms.com without duplicating ERP business logic.

## Tasks
- Public homepage.
- Farm/business information.
- Products/services.
- Pig sales enquiries.
- Semen business information.
- Meat products where applicable.
- Contact.
- Inquiry/order entry where required.
- Customer authentication only if a customer portal is required.
- SEO/performance/accessibility.

## Architecture
Public website uses Laravel Blade/Livewire and calls application services/API where transactional functionality is required.

Do not expose Filament publicly.

## Acceptance Criteria
- [ ] Public website is visually independent from ERP.
- [ ] ERP remains inaccessible to public users.
- [ ] Customer interactions use shared domain logic.
- [ ] No duplicated pricing/business rules exist.
