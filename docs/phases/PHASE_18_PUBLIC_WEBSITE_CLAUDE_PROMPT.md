# PHASE 18 - PUBLIC WEBSITE
## Claude Implementation Prompt
### Integrated Princess Azana Farms ERP

---

# 1. ROLE

You are implementing **Phase 18: Public Website** of the Integrated Princess Azana Farms ERP.

The repository is an existing Laravel application with the ERP development already organized into numbered phases under:

```text
docs/phases/
```

The public website is the **visitor-facing website for Integrated Princess Azana Farms Ltd**.

It is NOT the internal Filament ERP interface and must not turn the mobile/API application into a public-facing experience.

---

# 2. CRITICAL FIRST STEP

Before changing any code, inspect the existing project.

Read these files first:

```text
CLAUDE.md

docs/ERP_MASTER_PLAN.md
docs/ARCHITECTURE.md
docs/DATABASE_ARCHITECTURE.md
docs/DOMAIN_RULES.md
docs/API.md
docs/SECURITY.md
docs/IMPLEMENTATION_STATUS.md

docs/phases/PHASE_18_PUBLIC_WEBSITE.md
```

Then inspect:

```text
app/
bootstrap/
config/
database/
resources/
routes/
public/
storage/
composer.json
package.json
vite.config.*
```

Also inspect the current implementation of earlier phases.

Do NOT assume that Phase 18 is being built from an empty Laravel project.

---

# 3. PHASE CONTROL

This is **Phase 18 only**.

Do not start implementing Phase 19 or Phase 20.

Do not redesign unrelated ERP modules.

Do not modify the mobile application unless a genuine shared dependency requires it.

Do not modify backend business logic unnecessarily.

If something required by the public website belongs to a later phase, document the dependency instead of silently implementing unrelated functionality.

---

# 4. PUBLIC WEBSITE PURPOSE

Build a polished, modern, responsive public website for:

**Integrated Princess Azana Farms Ltd**

The website is the public-facing presentation layer for the company.

It should communicate:

- modern agriculture
- livestock farming
- quality
- responsible farming
- operational excellence
- technology-enabled agriculture
- trust
- African identity
- growth
- professionalism

The website should feel like a serious agricultural company with a modern operation.

It must NOT look like:

- an ERP dashboard
- a Filament admin panel
- a generic farm template
- a marketplace
- a cartoon farming website
- an overly orange website
- a generic corporate template

---

# 5. EXISTING ERP ARCHITECTURE MUST BE PRESERVED

The project already follows a modular Laravel ERP architecture.

Do not create a separate Laravel application for the public website unless the existing architecture explicitly requires it.

Prefer the existing application structure and routing strategy.

The public website should coexist cleanly with:

```text
Internal ERP
API
Public Website
Mobile Application
```

The public website must not expose internal ERP functionality.

---

# 6. READ THE PHASE 18 SPECIFICATION

The existing file:

```text
docs/phases/PHASE_18_PUBLIC_WEBSITE.md
```

is the primary functional scope for this phase.

Treat it as authoritative for the ERP project.

The instructions in this file provide the **design and implementation direction** for the public-facing website.

If there is a conflict:

1. Preserve the project's master architecture.
2. Preserve security rules.
3. Preserve domain rules.
4. Follow the Phase 18 specification.
5. Ask/report when a requirement is genuinely ambiguous.

Do not silently invent business functionality.

---

# 7. BRAND IDENTITY

Use the supplied **Integrated Princess Azana Farms** logo as the primary visual reference.

The logo contains:

- a crowned pig
- orange circular framing
- golden/yellow elements
- warm brown pig illustration
- dark brown/black typography
- agricultural identity
- premium royal visual cues

The logo must remain recognizable and must not be distorted.

---

# 8. BRAND COLOR SYSTEM

Build the public website around the colors extracted from the supplied logo.

## Primary Orange

```text
#F08732
```

Use for:

- primary CTA buttons
- active states
- highlights
- key accents
- icons
- decorative elements

## Bright Golden Orange

```text
#F7A62E
```

Use for:

- secondary accents
- gradients
- hover states
- highlights

## Royal Gold

```text
#F3C63C
```

Use sparingly for:

- premium highlights
- statistics
- decorative details
- subtle accents

## Light Gold

```text
#F7E376
```

Use for:

- soft highlights
- subtle backgrounds
- decorative accents

## Deep Brown / Charcoal

```text
#261B14
```

Use for:

- primary headings
- navigation
- footer
- strong text
- dark sections

## Warm Pig Brown

```text
#D5A14C
```

Use as a supporting agricultural tone.

## Dark Pig Brown

```text
#B47E3D
```

Use as a secondary supporting color.

## Warm Cream

```text
#FBFBEA
```

Use for:

- section backgrounds
- soft panels
- alternating sections

## White

```text
#FFFFFF
```

Use for:

- clean content areas
- cards
- navigation
- contrast

---

# 9. COLOR BALANCE

Do NOT make the entire website orange.

The website should primarily use:

```text
White / Cream
Deep Brown
Orange
Gold
```

A good visual balance is approximately:

```text
55-65% white / cream
15-20% deep brown
10-15% orange
5-10% gold
```

The result should feel:

**clean + premium + agricultural + warm**

rather than loud or overly colorful.

---

# 10. DESIGN DIRECTION

The visual direction should be:

- clean
- slick
- modern
- premium
- agricultural
- sophisticated
- African
- trustworthy
- natural
- professional

Use:

- generous whitespace
- large editorial photography
- strong typography
- subtle shadows
- restrained rounded corners
- elegant cards
- subtle gradients
- strong section hierarchy
- smooth but restrained animation

Do not overuse:

- glassmorphism
- gradients
- rounded containers
- shadows
- animations
- decorative elements

The logo itself is visually detailed, so the website around it should be comparatively clean.

---

# 11. WEBSITE INFORMATION ARCHITECTURE

Build the public-facing experience around these primary areas:

```text
Home
About
Our Operations
Products / Services
Sustainability
Contact
```

Use the actual Phase 18 specification to determine which routes/pages are required.

Do not create unnecessary pages simply to make the site appear larger.

---

# 12. HOME PAGE

The homepage should contain the strongest representation of the company.

Recommended structure:

```text
Header
↓
Hero
↓
About / Introduction
↓
Our Operations
↓
Farming / Livestock
↓
Technology / Modern Agriculture
↓
Why Azana Farms
↓
Products / Services
↓
Sustainability
↓
Call to Action
↓
Contact
↓
Footer
```

Adapt this to the existing Phase 18 requirements.

---

# 13. HERO

Create a visually strong hero section.

Possible messaging direction:

### Eyebrow

```text
INTEGRATED PRINCESS AZANA FARMS
```

### Main headline

Use a strong message such as:

```text
Building the Future of Modern African Agriculture
```

Other acceptable directions include:

```text
Raising Quality. Growing Agriculture. Building the Future.
```

or

```text
Modern Farming. Quality Livestock. Sustainable Growth.
```

Choose the strongest option based on the final design.

Do not use all alternatives.

Supporting copy should communicate the company's agricultural and livestock focus without inventing unsupported facts.

Primary CTA:

```text
Explore Our Farm
```

Secondary CTA:

```text
Contact Us
```

---

# 14. ABOUT SECTION

Create a sophisticated introduction to Azana Farms.

The section should communicate:

- integrated agriculture
- livestock production
- quality
- responsible management
- modern operations
- long-term agricultural development

Do NOT invent:

- number of animals
- farm size
- years in operation
- revenue
- certifications
- awards
- production capacity
- locations
- partnerships

Only use information supported by the project documentation.

---

# 15. OUR OPERATIONS

Create a visually strong operations section.

Where supported by the project specification, present areas such as:

### Livestock Production

Healthy livestock and structured production management.

### Breeding & Genetics

Breeding and genetics operations supporting productive livestock.

### Feed & Nutrition

Feed and nutrition management supporting animal health and growth.

### Meat / Processing

Quality-focused processing and handling.

### Agricultural Management

Modern systems supporting visibility, traceability and operational control.

Each card may contain:

- image
- icon
- title
- short description
- optional CTA

Do not invent operational capabilities not supported by the project documentation.

---

# 16. TECHNOLOGY SECTION

The ERP itself should not be exposed as a technical product.

Instead, communicate that Azana Farms uses modern technology to improve:

- farm management
- livestock monitoring
- traceability
- feed management
- inventory
- production visibility
- operational decision-making

Possible heading:

```text
Technology Behind Better Farming
```

Keep this customer-facing.

Do not expose:

- API endpoints
- database schemas
- internal dashboards
- authentication details
- internal workflows
- infrastructure details

---

# 17. WHY AZANA FARMS

Create a strong value proposition section.

Potential themes supported by the project:

- Quality
- Responsible Farming
- Traceability
- Innovation
- Animal Care
- Reliability
- Operational Visibility

Do not create unsupported certifications or claims.

---

# 18. SUSTAINABILITY

Create a responsible farming section.

Possible themes:

- responsible livestock management
- efficient production
- resource management
- reduced waste
- animal welfare
- sustainable agricultural development

Do not claim official certifications unless documented.

---

# 19. PRODUCTS / SERVICES

Only show products/services supported by the project documentation.

Potential categories from the ERP specification include:

- live pigs
- piglets
- breeding stock
- semen/genetics
- meat
- related agricultural products

Before exposing a product publicly, confirm it is supported by the project documentation.

Do not fabricate product prices.

Do not fabricate inventory availability.

Do not turn this website into an e-commerce system unless explicitly required by Phase 18.

---

# 20. CONTACT

Build a polished contact/enquiry section.

Where actual company information exists, use it.

Possible fields:

```text
Name
Company
Email
Phone
Subject
Message
```

Do not invent:

- phone numbers
- email addresses
- office addresses
- social accounts
- opening hours

If the project does not yet contain these values, make them configurable rather than hardcoding fake information.

---

# 21. NAVIGATION

Create a clean sticky header.

Suggested structure:

```text
[AZANA FARMS LOGO]

Home
About
Operations
Products
Sustainability
Contact

[Get in Touch]
```

Mobile:

- hamburger menu
- accessible controls
- smooth open/close animation
- large touch targets

Keep navigation minimal.

---

# 22. FOOTER

Use the deep brown:

```text
#261B14
```

The footer should contain:

- logo
- short company description
- navigation
- contact information
- social links if configured
- copyright
- subsidiary relationship where appropriate

Use orange/gold accents.

---

# 23. IMAGE DIRECTION

Use professional agricultural imagery.

Preferred subjects:

- healthy pigs
- pig farming
- livestock
- animal care
- farm workers
- feed production
- modern agricultural facilities
- breeding/genetics
- meat processing
- African agriculture

Where possible, imagery should feel authentic to the African/Nigerian agricultural environment.

Avoid inconsistent stock photography.

Maintain one coherent photographic style.

---

# 24. LOGO

Use the official Azana Farms logo supplied with the project.

Never:

- stretch it
- distort it
- recolor it
- crop important portions
- add excessive effects
- reduce its legibility

If the logo is not yet available inside the project:

STOP and identify that the asset needs to be placed into the project.

Do not recreate the logo using text.

Recommended asset location if consistent with the existing project:

```text
public/images/branding/azana-farms-logo.png
```

Use the existing project asset conventions if different.

---

# 25. DESIGN TOKENS

Centralize the brand colors.

For CSS, use a design-token system similar to:

```css
:root {
    --color-primary: #F08732;
    --color-primary-dark: #D96F20;

    --color-gold: #F3C63C;
    --color-gold-light: #F7E376;

    --color-brown: #261B14;
    --color-brown-light: #B47E3D;

    --color-pig-brown: #D5A14C;

    --color-cream: #FBFBEA;
    --color-white: #FFFFFF;
}
```

Do not scatter raw color values throughout unrelated components.

Use the project's existing styling conventions where applicable.

---

# 26. COMPONENT ARCHITECTURE

Create reusable public-site components.

Examples:

```text
Header
MobileNavigation
Hero
Section
SectionHeading
CTAButton
FeatureCard
OperationCard
ProductCard
ImageCard
StatisticsCard
ContactForm
Footer
```

Do not over-abstract.

Components should be reusable where there is genuine repetition.

---

# 27. LARAVEL ARCHITECTURE

Respect the existing Laravel application architecture.

Do not put complex business logic inside Blade views.

Where data is required:

- use appropriate controllers/actions/services already established by the project
- use existing domain services where applicable
- keep presentation concerns in the public website layer

Do not duplicate ERP business rules.

Do not create a second implementation of inventory, sales, animal, finance or traceability logic simply for the website.

---

# 28. DATA RULE

The public website must not invent operational data.

If dynamic ERP data is exposed publicly in Phase 18, only expose information explicitly approved by the phase specification.

Never expose private internal information such as:

- animal-level records
- employee information
- internal costs
- supplier information
- customer private data
- financial data
- internal stock balances
- internal permissions
- internal operational notes

Public website data must be deliberately selected.

---

# 29. RESPONSIVE DESIGN

The site must be excellent on:

- desktop
- laptop
- tablet
- mobile

Do not simply shrink desktop layouts.

Mobile must be intentionally designed.

Pay special attention to:

- hero height
- image cropping
- typography
- navigation
- CTA buttons
- cards
- forms
- footer

---

# 30. ANIMATION

Use subtle animation only where it improves the experience.

Examples:

- hero reveal
- section fade-up
- image reveal
- button transitions
- card hover
- mobile menu transitions

Support:

```text
prefers-reduced-motion
```

Do not over-animate the site.

---

# 31. ACCESSIBILITY

Implement:

- semantic HTML
- keyboard navigation
- visible focus states
- accessible labels
- appropriate alt text
- sufficient contrast
- accessible navigation
- accessible forms
- reduced-motion support

Do not rely on color alone to communicate meaning.

---

# 32. SEO

Implement appropriate:

- page title
- meta description
- canonical URL where appropriate
- Open Graph metadata
- semantic headings
- descriptive image alt text
- structured data where appropriate

Use the actual company identity and verified project information.

Do not create fake SEO claims.

---

# 33. PERFORMANCE

Optimize the public website for production.

Consider:

- responsive images
- lazy loading
- modern image formats
- appropriate image dimensions
- font optimization
- minimal JavaScript
- caching where appropriate
- efficient Blade rendering
- production Vite build

Do not sacrifice visual quality unnecessarily.

---

# 34. SECURITY

The public website must not weaken ERP security.

Check:

- CSRF protection
- form validation
- output escaping
- rate limiting where appropriate
- spam protection for contact forms if available
- secure configuration
- no leaked internal endpoints
- no sensitive data in page source
- no credentials in frontend code

Never expose internal ERP routes or APIs unnecessarily.

---

# 35. WHAT NOT TO CHANGE

Unless required by Phase 18, do not modify:

```text
Animal Management
Breeding
Health
Feed
Inventory
Semen
Sales
Slaughter
Finance
Tasks
Reporting
Mobile API
Mobile App
Authentication
Core ERP domain logic
```

If an integration is necessary, use the existing architecture rather than duplicating logic.

---

# 36. NO FABRICATED COMPANY INFORMATION

This rule is mandatory.

Never invent:

- certifications
- awards
- farm size
- livestock population
- production volume
- employee count
- revenue
- customer names
- supplier names
- government partnerships
- physical locations
- phone numbers
- email addresses
- social media accounts

If information is missing, use neutral copy or configurable placeholders.

---

# 37. VISUAL QUALITY STANDARD

Before declaring Phase 18 complete, inspect the entire website visually.

Check desktop and mobile.

Look specifically for:

- excessive orange
- weak contrast
- poor image cropping
- inconsistent spacing
- inconsistent buttons
- inconsistent typography
- excessive shadows
- excessive rounded corners
- generic template appearance
- overly busy sections
- poor mobile navigation
- weak CTA hierarchy
- poor footer
- logo distortion

The final website should look like a **premium modern African agricultural company**, not an AI-generated template.

---

# 38. TESTING

Run all appropriate project checks.

At minimum:

```text
PHP syntax / application checks
Laravel tests
TypeScript checks if applicable
ESLint if configured
Vite build
Production asset build
```

Also test:

- homepage
- all public routes
- navigation
- mobile navigation
- contact form
- validation
- error states
- responsive layout
- image loading
- 404 page if applicable

Fix errors introduced by this phase.

---

# 39. PHASE COMPLETION

When Phase 18 is complete:

1. Summarize files created.
2. Summarize files modified.
3. Summarize public routes created.
4. Summarize components created.
5. Summarize design system changes.
6. Summarize testing performed.
7. List any missing business information.
8. List any API/backend dependencies.
9. Update:

```text
docs/IMPLEMENTATION_STATUS.md
```

10. Mark Phase 18 accurately.

Do not mark the phase complete if critical functionality is broken.

Do not start Phase 19 automatically.

---

# 40. FINAL INSTRUCTION

Build the public website as a **clean, slick, premium, modern agricultural company website**.

The visual identity must clearly derive from the Azana Farms logo:

```text
Orange
Gold
Deep Brown
Warm Cream
White
```

The result should communicate:

**Trust + Quality + Agriculture + Modern Operations + African Identity + Growth**

The public website should make a visitor immediately understand:

> **Integrated Princess Azana Farms is a serious, modern, quality-focused agricultural business.**

Keep the design sophisticated.

Use whitespace.

Use strong photography.

Use the logo correctly.

Do not make everything orange.

Do not invent facts.

Do not break the ERP architecture.

Do not proceed beyond Phase 18.
