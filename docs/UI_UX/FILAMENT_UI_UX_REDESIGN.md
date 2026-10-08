# Azana Farms ERP
# Filament Backend UI/UX Redesign

## 1. ROLE

You are working on the existing **Integrated Princess Azana Farms ERP** Laravel + Filament application.

Your task is to significantly improve the **visual design, usability, information hierarchy and overall UI/UX of the Filament backend**.

The current Filament interface feels too plain, generic and unfinished.

I want it to look like a **modern, polished, premium enterprise ERP application** while preserving all existing functionality and business logic.

---

# 2. CRITICAL RULE

DO NOT rebuild the ERP.

DO NOT change the application's business logic.

DO NOT change database structure unless absolutely required for a UI feature.

DO NOT change existing workflows simply because you prefer another approach.

DO NOT remove existing functionality.

DO NOT rename models, resources, actions or permissions unnecessarily.

DO NOT replace Filament with another admin framework.

DO NOT create a completely separate frontend application.

The objective is:

> **Improve the existing Filament backend's UI/UX without breaking the ERP.**

---

# 3. FIRST: INSPECT THE EXISTING APPLICATION

Before modifying anything, inspect the entire existing project.

Read:

```text
CLAUDE.md

docs/ERP_MASTER_PLAN.md
docs/ARCHITECTURE.md
docs/DATABASE_ARCHITECTURE.md
docs/DOMAIN_RULES.md
docs/SECURITY.md
docs/IMPLEMENTATION_STATUS.md
```

Also inspect the existing phase documentation and current ERP implementation.

Inspect:

```text
app/Filament/
app/Models/
app/Livewire/
resources/
resources/views/
public/
config/
routes/
composer.json
package.json
```

Inspect:

- Filament Panels
- Resources
- Pages
- Widgets
- Forms
- Tables
- Actions
- Navigation
- Dashboard
- Notifications
- Relation managers
- Custom Livewire components
- Existing CSS
- Existing JavaScript
- Existing theme configuration

Understand what already exists before changing it.

---

# 4. DESIGN OBJECTIVE

Transform the backend from a default-looking Filament interface into a polished:

**Modern Agricultural Enterprise Management Platform**

The UI should feel:

- Premium
- Clean
- Professional
- Modern
- Efficient
- Data-focused
- Agricultural
- Sophisticated
- Easy to navigate
- Easy to scan
- Appropriate for managers and operational staff

It should look like a serious enterprise application used by:

- Directors
- Farm managers
- Supervisors
- Accountants
- Procurement staff
- Inventory staff
- Veterinary/health staff
- Production staff
- Sales staff
- Administrators

---

# 5. IMPORTANT DISTINCTION

The public Azana Farms website and the ERP backend have different purposes.

The public website should be:

> Brand-focused, visual and marketing-oriented.

The ERP should be:

> Operational, data-focused, efficient and professional.

Do NOT copy the public website design directly into Filament.

Instead, take the Azana Farms brand identity and create a sophisticated enterprise UI around it.

---

# 6. AZANA FARMS BRAND COLORS

Use the existing Azana Farms logo as the visual reference.

Primary colors:

```text
Primary Orange:       #F08732
Bright Golden Orange: #F7A62E
Royal Gold:           #F3C63C
Light Gold:           #F7E376

Deep Brown:           #261B14
Pig Brown:            #D5A14C
Dark Pig Brown:       #B47E3D

Warm Cream:           #FBFBEA
White:                #FFFFFF
```

---

# 7. COLOR USAGE

Do NOT turn the entire Filament dashboard orange.

That would make the ERP look amateurish.

Use:

### Deep Brown
`#261B14`

For:
- important headings
- strong text
- selected UI elements
- high-contrast areas

### Orange
`#F08732`

For:
- primary actions
- active states
- important highlights
- key CTAs
- selected navigation

### Gold
`#F3C63C`

Use sparingly for:
- highlights
- important KPIs
- subtle accents
- premium details

### Cream
`#FBFBEA`

Use for:
- subtle page backgrounds
- cards
- section backgrounds

### White

Use for:
- content surfaces
- cards
- tables
- forms
- navigation surfaces

The UI should remain predominantly:

**white + cream + neutral surfaces + deep brown**

with orange/gold used as controlled accents.

---

# 8. GLOBAL LAYOUT

Improve:

- sidebar
- top navigation
- content area
- breadcrumbs
- page headers
- notifications
- user menu
- navigation groups
- responsive behavior

The application should have strong visual hierarchy.

Avoid a cramped interface.

Use appropriate:

- padding
- spacing
- card separation
- typography
- borders
- subtle shadows

---

# 9. SIDEBAR REDESIGN

Make the sidebar visually polished.

Use the Azana Farms logo appropriately.

The sidebar should feel:

- clean
- organized
- premium
- easy to scan

Do not make the logo excessively large.

Review navigation grouping and make it logical.

Potential conceptual groups include:

### Overview
- Dashboard

### Farm Operations
- Farms / Locations
- Animals
- Breeding
- Farrowing
- Piglets
- Grower / Finisher
- Health
- Mortality
- Biosecurity

### Feed & Production
- Feed
- Feed Mill
- Feed Formulas
- Feed Production

### Inventory
- Raw Materials
- General Inventory
- Stock
- Stock Takes
- Transfers
- Adjustments

### Genetics & Semen
- Boars
- Semen Production
- Laboratory
- Quality Control
- Semen Inventory
- Semen Sales

### Sales
- Customers
- Pig Sales
- Slaughter
- Meat Processing
- Meat Sales

### Procurement
- Suppliers
- Purchase Requests
- Purchase Orders
- Goods Received

### Finance
- Finance
- Expenses
- Revenue
- Cash Flow
- Costing
- Budgets
- Profitability

### Management
- Tasks
- Alerts
- Approvals
- Reports
- Dashboards

### Administration
- Users
- Roles
- Permissions
- Audit
- Settings

IMPORTANT:

Use the actual resources already present in the project.

Do not create fake navigation items.

Do not create resources that do not exist.

---

# 10. NAVIGATION ICONS

Review every navigation item.

Use meaningful icons.

Examples:

- Animals: livestock/paw icon
- Breeding: appropriate reproduction/genetics icon
- Feed: nutrition/feed icon
- Inventory: warehouse/box icon
- Finance: currency/chart icon
- Sales: shopping/cart icon
- Health: medical icon
- Reports: chart icon
- Users: users icon
- Administration: settings icon

Choose icons actually available in the project's Filament/icon system.

---

# 11. NAVIGATION BADGES

Where existing data supports it, use useful badges for:

- pending approvals
- overdue tasks
- low stock
- pending procurement
- unresolved alerts

Do not create expensive database queries for badges.

Avoid badges that cause performance problems.

---

# 12. DASHBOARD REDESIGN

The dashboard should be the strongest page in the ERP.

It should answer:

> WHAT is happening?

> WHY does it matter?

> WHAT needs attention?

> WHAT should management do next?

Do not create a dashboard consisting of 20 random statistic cards.

Create a clear hierarchy.

Recommended structure:

```text
Welcome / Context
        ↓
Critical Alerts
        ↓
Key KPIs
        ↓
Production Overview
        ↓
Herd Overview
        ↓
Feed / Inventory
        ↓
Sales / Revenue
        ↓
Health / Mortality
        ↓
Tasks / Approvals
        ↓
Management Insights
```

Use actual available data.

Never invent metrics.

---

# 13. KPI CARDS

Create visually refined KPI cards where the existing data supports them.

Possible KPIs:

- Total Active Animals
- Sows
- Boars
- Growers
- Finishers
- Piglets
- Mortality
- Feed Consumption
- Feed Stock
- Low Stock Items
- Pending Approvals
- Sales
- Revenue
- Expenses
- Profitability

Only display KPIs that can be correctly calculated from existing application data.

Do not fabricate values.

---

# 14. KPI CARD DESIGN

Use visual hierarchy.

Primary KPI:

- large number
- small label
- context/trend where real data exists
- supporting icon

Secondary KPI:

- moderate number
- short label
- status indicator

Use subtle colors.

Do not make every card brightly colored.

---

# 15. STATUS COLORS

Use consistent semantic colors:

Success:
- green

Warning:
- amber/gold

Danger:
- red

Information:
- blue

Primary:
- Azana orange

Do not use orange for every status.

Status colors must have consistent meaning throughout the ERP.

---

# 16. ALERTS

Create a visually obvious but elegant management alert section.

Potential examples, only when actual data supports them:

- Low stock
- Overdue tasks
- Pending approvals
- High mortality
- Health issues
- Feed shortages
- Procurement delays
- Unresolved conflicts

The objective is not to show more information.

The objective is to show information that requires attention.

---

# 17. DATA VISUALIZATION

Where existing data supports it, improve dashboards with meaningful charts.

Potential charts:

- herd population trend
- mortality trend
- feed consumption
- weight/ADG
- FCR
- sales trend
- revenue vs expenses
- inventory movement
- production output

Every chart must answer a management question.

Do not add charts merely because charts look attractive.

---

# 18. FILAMENT RESOURCE PAGES

Improve all existing Filament Resources consistently.

Every resource should have:

- clean page heading
- useful description
- logical filters
- sensible table columns
- appropriate badges
- good empty states
- clear primary action
- grouped secondary actions

---

# 19. TABLE REDESIGN

Tables are critical for ERP usability.

Improve:

- column order
- column labels
- alignment
- numeric formatting
- date formatting
- badges
- status indicators
- row actions
- bulk actions
- filters
- search
- pagination

Avoid showing every possible field by default.

Show the most useful fields first.

Move secondary information into view pages, relation managers or other appropriate record views.

---

# 20. TABLE DENSITY

Provide a comfortable ERP table density.

Users should be able to scan many records without feeling cramped.

Use:

- readable row heights
- subtle separators
- appropriate typography
- consistent alignment

Numbers should align naturally.

Dates should be formatted consistently.

Money should be formatted consistently.

Quantities should be formatted consistently.

---

# 21. STATUS BADGES

Use polished badges for statuses such as:

```text
Active
Inactive
Pending
Approved
Rejected
Completed
Cancelled
Low Stock
Healthy
Sick
Deceased
In Production
Available
Sold
```

Only use statuses that actually exist in the application.

Use semantic colors.

Do not randomly assign colors.

---

# 22. FILTERS

Improve filters using:

- searchable selects
- grouped filters
- date ranges
- status filters
- location filters
- production unit filters
- category filters

only where supported by existing data.

Do not overload every resource with unnecessary filters.

---

# 23. FORMS

Forms should feel clean and easy to use.

Use logical sections.

For complex resources, organize fields into appropriate sections such as:

```text
Basic Information

Identification

Production Information

Health Information

Location

Financial Information

Additional Information
```

Only use sections relevant to each resource.

Avoid giant unstructured forms.

---

# 24. FORM UX

Improve:

- labels
- helper text
- placeholders
- validation messages
- field grouping
- conditional fields
- reactive fields
- searchable relationships

Use sensible defaults only where business rules permit them.

Do not invent defaults that could incorrectly alter business data.

---

# 25. CREATE / EDIT PAGES

Create and edit pages should clearly communicate context.

Example:

```text
Create Animal

Basic Information
-----------------

Animal Number
Category
Breed
Sex

Production
-----------------

...

Location
-----------------

...
```

Avoid presenting dozens of fields as one long vertical wall.

---

# 26. RECORD VIEW PAGES

For important ERP records, make the record view feel like a proper operational profile.

For example, an animal record can visually organize:

```text
Animal Identity
        ↓
Current Status
        ↓
Current Location
        ↓
Production Information
        ↓
Health
        ↓
Breeding
        ↓
Movement
        ↓
Related Records
```

Use tabs, sections and relation managers appropriately.

Do not invent relationships.

---

# 27. ANIMAL PROFILE UI

Animals are central to this ERP.

Where supported by the existing implementation, make animal records especially polished.

Use:

- animal identification
- status
- category
- breed
- sex
- location
- current weight
- health state
- breeding information
- related events

Do not add fields simply because they would look good.

---

# 28. INVENTORY UI

Inventory should feel like an operational warehouse system.

Improve:

- stock cards
- item status
- stock levels
- low-stock indicators
- stock movements
- batches
- locations
- stock takes

Where data supports it, distinguish:

```text
Healthy Stock
Low Stock
Critical Stock
Out of Stock
```

Do not change inventory calculations.

The existing inventory ledger/business logic remains authoritative.

---

# 29. PROCUREMENT UI

Make procurement workflows easy to understand.

Where the existing implementation supports it, visually communicate:

```text
Request
    ↓
Approval
    ↓
Purchase Order
    ↓
Goods Received
    ↓
Inventory
```

Do not change the actual workflow logic.

This is a UI/UX improvement only.

---

# 30. BREEDING UI

Breeding workflows should be visually understandable.

Where supported, emphasize:

- sow
- boar
- service
- expected farrowing
- farrowing
- litter
- piglets
- weaning

Use timelines or structured sections where useful.

Do not invent data.

---

# 31. HEALTH UI

Health records should be easy to scan.

Use appropriate:

- status badges
- treatment records
- vaccination records
- mortality records
- alerts

Do not turn the health module into a generic medical application.

It is part of a livestock ERP.

---

# 32. FINANCE UI

Finance must remain professional and restrained.

Use:

- clean financial tables
- properly formatted currency
- clear totals
- visual hierarchy
- revenue/expense summaries
- cash-flow views
- profitability indicators

Do not use excessive bright colors.

Money should be visually easy to scan.

---

# 33. REPORTING UI

Reports should feel like management tools.

Use:

- clear filters
- date range selectors
- summary metrics
- clean tables
- export actions
- charts where meaningful

Do not create decorative charts without useful information.

---

# 34. EMPTY STATES

Replace ugly/default empty states with helpful messages.

Example:

```text
No animals found

There are no animals matching the current filters.

[Clear Filters]
```

Keep empty states concise.

---

# 35. LOADING STATES

Use polished loading states.

Avoid abrupt blank screens.

Use skeletons or appropriate loading indicators.

Do not overuse animations.

---

# 36. NOTIFICATIONS

Improve Filament notifications visually and consistently.

Notifications should clearly communicate:

- success
- warning
- error
- information

Messages should be concise and actionable.

---

# 37. GLOBAL SEARCH

If global search already exists, improve its presentation and usefulness.

If supported by the current architecture, make it easy to find:

- animals
- customers
- suppliers
- inventory items
- tasks
- relevant ERP records

Do not implement expensive global search without considering performance.

---

# 38. RESPONSIVE DESIGN

The Filament backend must work well on:

- desktop
- laptop
- tablet

Mobile support should remain practical for operational users where applicable.

Pay special attention to:

- tables
- filters
- forms
- sidebar
- record pages
- dashboard widgets

---

# 39. TYPOGRAPHY

Use typography to establish hierarchy.

Recommended approach:

- strong page headings
- medium-weight section headings
- readable body text
- smaller muted supporting text
- clear numeric hierarchy

Avoid excessively large typography.

ERP interfaces need information density without visual clutter.

---

# 40. CARDS

Cards should have a purpose.

Use cards for:

- KPIs
- summaries
- alerts
- key record information
- workflow status

Do not put every table or every piece of information into a card.

Too many cards make the ERP feel like a dashboard template.

---

# 41. BORDERS AND SHADOWS

Use subtle visual separation.

Prefer:

- light borders
- subtle shadows
- clean surfaces

Avoid:

- heavy shadows
- thick borders
- glowing effects
- excessive gradients

---

# 42. DARK MODE

If dark mode is already supported, make sure it is intentionally designed.

Ensure:

- contrast
- readable tables
- readable forms
- proper badges
- appropriate brand accents

If dark mode is not currently implemented, do not make it a reason to rewrite the application.

---

# 43. AZANA FARMS BRANDING

The backend should have recognizable Azana Farms branding.

Use the logo and brand colors subtly in:

- sidebar branding
- primary actions
- active navigation
- selected tabs
- dashboard accents
- important highlights

Do not make the ERP look like the marketing website.

---

# 44. PROFESSIONAL ERP FEEL

Use modern enterprise applications as inspiration.

The interface should communicate:

```text
Operational Control
Data Visibility
Efficiency
Trust
Professional Management
```

The user should feel that this system is managing a serious agricultural business.

---

# 45. PERFORMANCE

Do not sacrifice performance for visual design.

Avoid:

- unnecessary database queries
- expensive dashboard calculations
- N+1 queries
- excessive Livewire reactivity
- unnecessary JavaScript
- huge images
- excessive animations

Pay special attention to dashboard widgets and navigation badges.

---

# 46. DO NOT DUPLICATE BUSINESS LOGIC

The UI must not implement business rules that belong in:

```text
Domain
Actions
Services
Models
Policies
```

The UI should consume existing business logic.

Do not move business logic into Blade templates.

Do not move business logic into Filament Resource classes simply to make the UI work.

---

# 47. SECURITY

Do not weaken:

- permissions
- authorization
- policies
- roles
- audit trails
- sensitive data protections

A UI redesign must not bypass authorization.

Hiding a navigation item is not authorization.

Existing server-side authorization must remain intact.

---

# 48. NO FAKE DATA

Do not create fake:

- KPI values
- dashboard statistics
- animals
- sales
- inventory
- finance values
- charts
- alerts

If a widget needs data that does not exist:

1. identify the missing data source
2. report it
3. do not fabricate values

---

# 49. IMPLEMENTATION STRATEGY

Do not modify the entire application at once without validation.

Work in controlled stages.

Recommended order:

## Stage 1
Global theme

## Stage 2
Application shell

## Stage 3
Sidebar/navigation

## Stage 4
Dashboard

## Stage 5
Reusable UI patterns

## Stage 6
Major resources

## Stage 7
Forms

## Stage 8
Tables

## Stage 9
Record pages

## Stage 10
Reports

## Stage 11
Responsive behavior

## Stage 12
Final visual polish

After each stage:

- inspect the result
- run checks
- fix regressions
- continue only when stable

---

# 50. DO NOT MASS-REFACTOR

Do not rewrite every Filament Resource because the code could be cleaner.

Prefer targeted UI improvements.

Preserve the existing architecture.

---

# 51. VISUAL CONSISTENCY

Create reusable patterns for:

### Buttons
- Primary
- Secondary
- Danger
- Ghost

### Badges
- Success
- Warning
- Danger
- Info
- Neutral

### Sections
- Header
- Description
- Content

### Cards
- Title
- Metric
- Context
- Action

### Tables
- Header
- Filters
- Rows
- Actions
- Pagination

Everything should feel like one cohesive application.

---

# 52. DASHBOARD PERSONALIZATION

Where the existing architecture supports it, consider role-specific dashboard emphasis.

### Management

Focus on:
- herd
- production
- revenue
- costs
- profitability
- alerts

### Farm Supervisor

Focus on:
- animals
- health
- breeding
- tasks
- feed
- mortality

### Inventory

Focus on:
- stock
- low stock
- procurement
- stock movements

### Accountant

Focus on:
- revenue
- expenses
- cash flow
- approvals
- profitability

Only implement role-specific behavior if the current role/permission architecture supports it cleanly.

Do not create fake permissions.

---

# 53. FINAL VISUAL AUDIT

After implementation, inspect the entire application visually.

## Dashboard
- Is it visually impressive?
- Is information hierarchy clear?
- Are important metrics obvious?
- Are alerts visible?
- Is it too crowded?

## Sidebar
- Is navigation easy to understand?
- Are groups logically organized?
- Does branding look good?
- Is it too wide?
- Is it too crowded?

## Tables
- Are columns useful?
- Are statuses clear?
- Are filters easy?
- Is the table too dense?

## Forms
- Are fields logically grouped?
- Is the form overwhelming?
- Are required fields obvious?

## Record Pages
- Is important information immediately visible?
- Are related records easy to navigate?

## Colors
- Is orange being overused?
- Are semantic colors consistent?
- Is contrast good?

## Typography
- Is hierarchy obvious?
- Is text readable?

## Overall

Ask:

> Does this look like a serious enterprise agricultural ERP?

If the answer is no, continue refining.

---

# 54. TESTING

Before completion, run the project's appropriate checks.

At minimum, use the commands that actually exist in the project, such as:

```bash
php artisan test
php artisan optimize:clear
npm run build
```

Also run configured tools such as:

```text
PHPStan
Pint
ESLint
TypeScript
```

Do not assume every command exists.

Check:

- dashboard
- navigation
- major resources
- forms
- tables
- filters
- actions
- permissions
- notifications
- responsive layout

Fix errors introduced by the redesign.

---

# 55. REGRESSION CHECK

Confirm that UI work has not broken:

- authentication
- authorization
- roles
- permissions
- audit logging
- CRUD
- relationships
- business actions
- reports
- exports
- workflows

If anything breaks, fix it before completion.

---

# 56. IMPLEMENTATION STATUS

When finished, update:

```text
docs/IMPLEMENTATION_STATUS.md
```

Record:

- what was redesigned
- files changed
- components added
- theme changes
- dashboard changes
- navigation changes
- tests performed
- remaining UI limitations

Do not mark unrelated ERP phases as complete.

---

# 57. FINAL INSTRUCTION

The objective is NOT simply to make Filament colorful.

The objective is to make the Azana Farms ERP feel like a **purpose-built, premium enterprise agricultural management platform**.

The final UI should be:

**Clean**
+
**Professional**
+
**Modern**
+
**Fast**
+
**Data-focused**
+
**Easy to navigate**
+
**Visually polished**
+
**Clearly branded as Azana Farms**

Use the Azana Farms logo and brand palette as inspiration, but keep the backend restrained and professional.

Preserve the existing ERP architecture and functionality.

Do not invent data.

Do not invent business logic.

Do not break existing workflows.

Do not proceed into unrelated development phases.

Build the UI carefully, inspect the result visually, and continue refining until it looks genuinely production-ready.
