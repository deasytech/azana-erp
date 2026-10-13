<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Azana Farms User Guide</title>
<meta name="description" content="How to use the Azana Farms ERP: signing in, finding your way around, and doing each job from animals and feed to sales and finance.">
<script>try{var t=localStorage.getItem('azana-docs-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Manrope:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/api-docs.css') }}?v={{ @filemtime(public_path('css/api-docs.css')) }}">
<link rel="stylesheet" href="{{ asset('css/user-guide.css') }}?v={{ @filemtime(public_path('css/user-guide.css')) }}">
</head>
<body>
<div class="progress" id="progress"></div>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="shell">
  <header class="topbar">
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 4.5h14M2 9h14M2 13.5h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </button>
    <div class="mark" aria-hidden="true"></div>
    <span class="name">Azana Farms</span>
    <span class="tag">User guide</span>
    <div class="spacer"></div>
    <a class="back" href="{{ url('/admin') }}">&larr; Back to the ERP</a>
    <button class="theme-toggle" id="themeToggle" aria-label="Switch between light and dark">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13.5 9.5A5.5 5.5 0 0 1 6.5 2.5a5.5 5.5 0 1 0 7 7Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
    </button>
  </header>

  <div class="scrim" id="scrim"></div>

  <nav class="sidenav" id="sidenav" aria-label="Guide sections">
    <div class="search-wrap">
      <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true"><circle cx="6.3" cy="6.3" r="4.5" stroke="currentColor" stroke-width="1.4"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
      <input id="navSearch" type="search" placeholder="Search the guide…  ( / )" aria-label="Search the guide">
    </div>
    <div id="navList">
      @foreach ($nav as $title => $items)
        <div class="nav-group">
          <div class="nav-group-title">{{ $title }}</div>
          @foreach ($items as [$id, $label, $words])
            <a class="nav-link" data-search="{{ $words }}" href="#{{ $id }}"><span class="p">{{ $label }}</span></a>
          @endforeach
        </div>
      @endforeach
    </div>
    <p class="nav-empty" id="navEmpty">Nothing matches. Try a simpler word, such as &ldquo;stock&rdquo; or &ldquo;pig&rdquo;.</p>
  </nav>

  <main id="main-content">
    <div class="content-inner">

      <section class="hero" id="top">
        <div class="eyebrow">USER GUIDE</div>
        <h1>Learn the Azana Farms ERP</h1>
        <p>One place for the whole farm: the pigs, their health and breeding, the feed mill, the stores, semen, sales, meat and the money. This guide shows you where everything is and how to do each job, step by step.</p>
        <div class="lead-actions">
          <a class="btn primary" href="#welcome">Start here</a>
          <a class="btn" href="#layout">How to find your way around</a>
          <a class="btn" href="#faq">Something not working?</a>
        </div>
      </section>

      <div class="cards">
        <a class="card" href="#signin"><div class="ico">&#128273;</div><h4>Sign in</h4><p>Your account, two-factor codes and your profile.</p></a>
        <a class="card" href="#layout"><div class="ico">&#129517;</div><h4>Find your way</h4><p>The menu, search, notifications and light or dark mode.</p></a>
        <a class="card" href="#m-animals"><div class="ico">&#128055;</div><h4>Daily work</h4><p>Every menu group explained, from Animals to Finance.</p></a>
        <a class="card" href="#w-sell"><div class="ico">&#129534;</div><h4>Step-by-step jobs</h4><p>Make feed, follow a pig, sell, and close the month.</p></a>
      </div>

      {{-- ============================================================ GETTING STARTED --}}
      <div class="group-title">Getting started</div>

      <section class="chapter" id="welcome">
        <div class="eyebrow">Getting started</div>
        <h2>Welcome</h2>
        <p class="lead">The Azana Farms ERP replaces notebooks and spreadsheets with one system where every pig, every bag of feed and every naira is recorded once and can be followed from start to finish.</p>

        <h3>What it does for you</h3>
        <ul>
          <li><b>Knows every animal.</b> Each pig has a permanent number, its tags, where it is, what it weighs, and its whole history.</li>
          <li><b>Plans for you.</b> Enter a service and it works out when to check for pregnancy, when to expect farrowing, weaning, the next heat and the next service.</li>
          <li><b>Protects you from mistakes.</b> A pig on medicine cannot be sold or slaughtered until its withdrawal period ends; semen that failed quality control cannot be sold; credit limits are checked.</li>
          <li><b>Counts stock and money honestly.</b> Stock and accounts are ledgers, so the numbers on every report agree with each other.</li>
          <li><b>Traces anything.</b> From a pack of meat on an invoice back to the carcass, the pig, its mother, the semen, the feed and the supplier.</li>
        </ul>

        <h3>Three ways in, one set of records</h3>
        <div class="cards">
          <div class="card"><div class="ico">&#128187;</div><h4>The ERP (this system)</h4><p>For staff, on a computer or tablet. Everything in this guide.</p></div>
          <div class="card"><div class="ico">&#128241;</div><h4>The mobile app</h4><p>For workers in the pens: scan a tag, record a weight or a death, even with no signal. See <a href="#mobile" style="color:var(--accent)">the mobile app</a>.</p></div>
          <div class="card"><div class="ico">&#127760;</div><h4>The public website</h4><p>Shows the farm and what is for sale. Managed from the Website menu.</p></div>
        </div>

        <div class="note tip"><b>Good to know</b><p>You only see the menus your role allows. If a colleague mentions a screen you cannot find, it is probably not part of your role. See <a href="#roles" style="color:inherit;text-decoration:underline">Who can see what</a>.</p></div>
      </section>

      <section class="chapter" id="signin">
        <div class="eyebrow">Getting started</div>
        <h2>Signing in</h2>
        <p class="lead">Your administrator creates your account and gives you a role. You sign in with your email address and password.</p>
        <x-guide.shot name="login.png" caption="The sign-in page. Enter your email address and password." />

        <ol class="steps">
          <li><b>Open the ERP address</b> your administrator gave you and go to the sign-in page.</li>
          <li><b>Enter your email and password</b>, then press <b>Sign in</b>.</li>
          <li><b>If your role uses two-factor sign-in</b>, you are asked for a six-digit code from your authenticator app (see below).</li>
          <li>You land on <b>Home</b>, titled &ldquo;Today on the farm&rdquo;.</li>
        </ol>

        <h3>Two-factor sign-in</h3>
        <p>For the roles that handle money, semen release and the whole business (<b>Owner/Director, General Manager, Accountant and Semen Laboratory Manager</b>), a password alone is not enough. The first time, the system shows a QR code to scan with an authenticator app (Google Authenticator, Microsoft Authenticator, Authy). After that you enter the current code each time you sign in.</p>
        <div class="note warn"><b>Save your recovery codes</b><p>When you set up two-factor, the system gives you recovery codes. Keep them somewhere safe and private. If you lose your phone they are the way back in; without them an administrator must reset your account.</p></div>

        <h3>Your profile and password</h3>
        <p>Open the <b>user menu</b> (your name, top right) and choose <b>Profile</b> to change your name, email or password, and to manage two-factor sign-in. Use the same menu to <b>sign out</b> when you leave a shared computer.</p>
        <div class="note"><b>Forgot your password?</b><p>Use the &ldquo;Forgot password?&rdquo; link on the sign-in page. If you cannot get the email, ask your administrator to reset it. Accounts that are switched off cannot sign in at all.</p></div>
      </section>

      <section class="chapter" id="layout">
        <div class="eyebrow">Getting started</div>
        <h2>Finding your way around</h2>
        <p class="lead">Every screen has the same frame, so once you know it you can work anywhere in the system.</p>
        <x-guide.shot name="home.png" caption="Home: the sidebar on the left, the top bar above, and your working area in the middle." />

        <div class="legend">
          <div><b class="n">1</b><span><strong>Sidebar menu</strong>All screens, grouped by area of the farm. Click a group name to open or close it. The arrow at the top collapses the whole sidebar to give you more room.</span></div>
          <div><b class="n">2</b><span><strong>Search</strong>Press <kbd>Ctrl</kbd>+<kbd>K</kbd> (<kbd>&#8984;</kbd>+<kbd>K</kbd> on a Mac) to find any animal, customer, supplier, item or record by typing a few letters.</span></div>
          <div><b class="n">3</b><span><strong>Notifications</strong>The bell shows what the system needs from you: approvals waiting, alerts, finished imports. It refreshes about once a minute.</span></div>
          <div><b class="n">4</b><span><strong>User menu</strong>Your profile, light or dark mode, and sign out.</span></div>
          <div><b class="n">5</b><span><strong>Page title and buttons</strong>The page name and the main action, for example <b>New invoice</b>, at the top right of the work area.</span></div>
          <div><b class="n">6</b><span><strong>User guide</strong>The last item in the sidebar. It opens this guide in a new tab.</span></div>
        </div>

        <x-guide.shot name="search.png" caption="Global search: type part of a name, tag number or code and jump straight to the record." />

        <h3>The menu, in the order the farm works</h3>
        <div class="table-wrap"><table class="kv">
          <tr><th>Menu group</th><th>What you do there</th></tr>
          <tr><td>Animals</td><td>Register, identify, weigh and move pigs.</td></tr>
          <tr><td>Farm structure</td><td>Farms, buildings, rooms, pens and production units.</td></tr>
          <tr><td>Breeding</td><td>Heat, services, farrowing, litters and the breeding calendar.</td></tr>
          <tr><td>Health</td><td>Vaccination, treatment, vet visits, deaths, culling, quarantine, lab results.</td></tr>
          <tr><td>Biosecurity</td><td>Visitor log and inspections.</td></tr>
          <tr><td>Production</td><td>Grower and finisher batches, feed given, weigh-ins.</td></tr>
          <tr><td>Feed mill</td><td>Formulas, production orders and finished feed.</td></tr>
          <tr><td>Inventory</td><td>Items, stores, stock on hand, adjustments and counts.</td></tr>
          <tr><td>Purchasing</td><td>Suppliers, requests, orders, goods received, invoices and payments.</td></tr>
          <tr><td>Semen</td><td>Boars, collections, quality control and stock.</td></tr>
          <tr><td>Sales</td><td>Customers, orders, invoices and payments received.</td></tr>
          <tr><td>Slaughter &amp; meat</td><td>Slaughter days, carcasses, meat products and tracing.</td></tr>
          <tr><td>Finance</td><td>Journal, accounts, cash, expenses, budgets and reports.</td></tr>
          <tr><td>Tasks &amp; alerts</td><td>Your tasks, alerts, approvals and the mobile sync log.</td></tr>
          <tr><td>Management</td><td>Dashboards, targets and the monthly report.</td></tr>
          <tr><td>Website</td><td>Listings and enquiries for the public site.</td></tr>
          <tr><td>Master data, Configuration, Administration</td><td>Lists, settings, prices, users, roles, backups. Mostly for managers and administrators.</td></tr>
          <tr><td>Help</td><td>This guide.</td></tr>
        </table></div>
        <div class="note tip"><b>Tip</b><p>Groups used less often (Farm structure, Biosecurity, Feed mill, Semen, Slaughter &amp; meat, and the set-up groups) start folded. Click the name to open them.</p></div>

        <h3>Light and dark mode</h3>
        <p>Choose light, dark or &ldquo;system&rdquo; from the user menu in the ERP. This guide has its own switch in the top bar and remembers your choice.</p>
      </section>

      <section class="chapter" id="lists">
        <div class="eyebrow">Getting started</div>
        <h2>Working with lists</h2>
        <p class="lead">Most screens open as a list: animals, invoices, purchase orders. They all work the same way.</p>
        <x-guide.shot name="animals-list.png" caption="A typical list: search box, filters, sortable columns and a button to add a record." />

        <ul>
          <li><b>Search</b> box above the table finds records by their main words (name, code, tag).</li>
          <li><b>Filters</b> (the funnel icon) narrow the list, for example animals that are <i>Active</i> in one pen, or invoices that are <i>Unpaid</i>. Active filters show as small tags you can remove.</li>
          <li><b>Click a column heading</b> that has an arrow to sort. Click again to reverse.</li>
          <li><b>Columns button</b> shows or hides columns, so you see only what you need.</li>
          <li><b>Click a row</b> (or its <b>View</b> link) to open the record. The <b>&hellip;</b> menu at the end of a row has the other things you may do with it.</li>
          <li><b>Pagination</b> at the bottom: choose how many rows per page and move between pages.</li>
          <li><b>Export</b> (where you have the permission) downloads the list for Excel. The file respects your filters.</li>
        </ul>
        <x-guide.shot name="animal-view.png" caption="A record page: the details on top, and tabs below for its history, weights, health and more." />
        <p>An opened record shows its details at the top and <b>tabs</b> (or tables) below for everything connected to it: an animal&rsquo;s weights, status history, movements and identifiers; an invoice&rsquo;s lines and payments applied.</p>
      </section>

      <section class="chapter" id="forms">
        <div class="eyebrow">Getting started</div>
        <h2>Saving and correcting records</h2>
        <p class="lead">Adding a record is simple. Correcting one follows one important rule.</p>

        <h3>Creating and editing</h3>
        <ol class="steps">
          <li>Press the button at the top right of a list (<b>New &hellip;</b>, <b>Register animal</b>, <b>Record death</b>&hellip;).</li>
          <li>Fill in the form. Fields with a red star are required. Many fields are drop-downs you can type into to search.</li>
          <li>Press <b>Create</b> (or <b>Save changes</b> when editing). A green message confirms it, and a red message beside a field tells you what to fix.</li>
        </ol>
        <p>Forms ask only for what you must supply. Dates such as the expected farrowing, the totals on an invoice or the cost of a feed batch are <b>calculated for you</b> from the farm&rsquo;s settings and your entries.</p>

        <h3>The rule: history is never erased</h3>
        <div class="note warn"><b>You cannot delete stock or money</b><p>Stock movements, journal lines, payments and animal histories are permanent records. If you made a mistake, you <b>reverse, cancel or void</b> the record with the proper action. The system adds a new correcting line and keeps both, so the books always agree and the audit trail is complete.</p></div>
        <ul>
          <li>Look for actions like <b>Cancel</b>, <b>Void</b>, <b>Reverse</b> or <b>Correct</b> on the record, in its <b>&hellip;</b> menu or top-right actions.</li>
          <li>Some corrections need a <b>reason</b> and some need a <b>manager&rsquo;s approval</b> (see <a href="#m-tasks" style="color:var(--accent)">Tasks and alerts</a>).</li>
          <li>Everything is stamped with who did it and when. Administrators can see this in the <b>Audit log</b>.</li>
        </ul>

        <h3>Warnings and blocks</h3>
        <p>The system speaks up before a mistake becomes expensive. A <b>warning</b> lets you carry on after reading it. A <b>block</b> stops the action and says why, for example selling beyond a customer&rsquo;s credit limit, or selling a pig still under withdrawal. Read the message: it nearly always tells you what to do next.</p>
      </section>

      <section class="chapter" id="roles">
        <div class="eyebrow">Getting started</div>
        <h2>Who can see what</h2>
        <p class="lead">Your <b>role</b> decides which menus you see and what you can do on each screen: view, create, edit, delete, approve, export or print.</p>

        <div class="table-wrap"><table class="kv">
          <tr><th>Role</th><th>What it is for</th><th>Two-factor</th><th>Mobile app</th></tr>
          @foreach ($roles as $role)
            <tr>
              <td>{{ $role['name'] }}</td>
              <td>{{ $role['about'] }}</td>
              <td><span class="pill {{ $role['two_factor'] ? 'yes' : 'no' }}">{{ $role['two_factor'] ? 'Required' : 'No' }}</span></td>
              <td><span class="pill {{ $role['mobile'] ? 'yes' : 'no' }}">{{ $role['mobile'] ? 'Yes' : 'Web only' }}</span></td>
            </tr>
          @endforeach
        </table></div>

        <ul>
          <li>A <b>Farm Worker</b> sees day-to-day entry screens, not finance, users, backups or imports.</li>
          <li>A <b>Sales Officer</b> sees customers and sales, but not the accounting journal.</li>
          <li>An <b>Accountant</b> sees finance and costing, but cannot release semen.</li>
          <li>Only the <b>Owner</b> and the <b>General Manager</b> see <i>Backups &amp; monitoring</i>.</li>
          <li>Roles are edited by an administrator under <b>Administration &rarr; Roles</b>, so your own access may differ a little from this table.</li>
        </ul>
        <div class="note"><b>Cannot find a screen or button?</b><p>You most likely do not have permission for it. Ask your manager or the system administrator to check your role. Do not share accounts: every action is recorded against the person who signed in.</p></div>
      </section>

      {{-- ============================================================ MODULES --}}
      <div class="group-title">Module by module</div>

      <section class="chapter" id="m-home">
        <div class="eyebrow">Module</div>
        <h2>Home</h2>
        <p class="lead">&ldquo;Today on the farm&rdquo; is the first page you see. It collects what needs attention now so you can plan your day without opening ten screens.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Home']" /></div>
        <x-guide.shot name="home.png" caption="Home: today's tasks, alerts and key figures for your role." />
        <ul>
          <li>Boxes and charts show figures such as animals on the farm, what is due today, and open alerts.</li>
          <li>Click a figure or a row to jump to the records behind it.</li>
          <li>Different roles see different boxes, according to what they are allowed to view.</li>
        </ul>
      </section>

      <section class="chapter" id="m-animals">
        <div class="eyebrow">Module</div>
        <h2>Animals</h2>
        <p class="lead">The register of every pig on the farm. Each animal has one permanent number for life, however often its tags change.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Animals', 'Animals']" /></div>
        <x-guide.shot name="animals-list.png" caption="The animals list: filter by status, sex, category or pen." />

        <h3>Register an animal</h3>
        <ol class="steps">
          <li>Open <x-guide.path :items="['Animals']" /> and press <b>Register animal</b>.</li>
          <li>Choose the category (sow, boar, gilt, piglet&hellip;), breed, sex and date of birth. Add its mother and father if known, so its family line is traceable.</li>
          <li>Add its <b>identifiers</b>: ear tag, RFID, QR code, barcode or a manual ID. An animal can have several.</li>
          <li>Save. The system issues the permanent animal number.</li>
        </ol>

        <h3>On an animal&rsquo;s page</h3>
        <ul>
          <li><b>Identifiers</b>: add a new tag when one is lost. The animal number does not change.</li>
          <li><b>Weights</b>: record a weight, see growth over time.</li>
          <li><b>Movements</b>: move the animal to another pen or location, with a reason. The history of movements is permanent.</li>
          <li><b>Status history</b>: active, sold, dead, culled and so on, with dates.</li>
          <li>Tabs for its <b>health, breeding and production</b> records appear as they are added.</li>
        </ul>
        <div class="note tip"><b>Scanning</b><p>If you have a tag or QR code in your hand, type or scan it into the global search (<kbd>Ctrl</kbd>+<kbd>K</kbd>) to open the animal at once. On the mobile app, point the camera at the tag.</p></div>
      </section>

      <section class="chapter" id="m-structure">
        <div class="eyebrow">Module</div>
        <h2>Farm structure</h2>
        <p class="lead">Describes the farm itself, so animals can be placed and counted by where they are.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Farm structure']" /></div>
        <ul>
          <li><b>Farms</b> and <b>Locations</b>: the sites you operate.</li>
          <li><b>Buildings</b> and <b>Rooms</b>: for example a farrowing house with eight rooms.</li>
          <li><b>Pens</b>: the smallest place an animal is put. Set its capacity and the pen shows how full it is.</li>
          <li><b>Production units</b>: the business lines, such as pigs.</li>
        </ul>
        <p>This is set up once and changed only when the farm changes. Moves and pen counts elsewhere depend on it being right.</p>
      </section>

      <section class="chapter" id="m-breeding">
        <div class="eyebrow">Module</div>
        <h2>Breeding</h2>
        <p class="lead">From a sow coming on heat to piglets weaned, with every important date worked out for you.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Breeding']" /><span>Heat detection &middot; Services &middot; Litters &middot; Breeding calendar</span></div>
        <x-guide.shot name="breeding-services.png" caption="Services: each mating or insemination and the dates the system expects next." />

        <ol class="steps">
          <li><b>Record heat.</b> <x-guide.path :items="['Breeding','Heat detection']" /> &rarr; <b>Record heat</b>. Choose the sow and the date.</li>
          <li><b>Record the service.</b> <x-guide.path :items="['Breeding','Services']" /> &rarr; <b>Record service</b>. Pick the sow, the boar or semen batch, the date, the technician and the method. The system now shows the expected pregnancy check, farrowing, weaning, next heat and next service dates.</li>
          <li><b>Confirm pregnancy</b> at the check date on the service record (or mark a return to heat).</li>
          <li><b>Record farrowing.</b> This creates the <b>litter</b>: number born alive, stillborn and mummified, and birth weights.</li>
          <li><b>Record losses and weaning.</b> On the litter, add piglet deaths and, at weaning, the number weaned and their weights. Mortality and weaning percentages and average weights are calculated.</li>
        </ol>
        <x-guide.shot name="breeding-calendar.png" caption="The breeding calendar shows what is due each day, so no check is missed." />
        <p>The <b>Breeding calendar</b> lays all the expected dates on a calendar. The durations (for example, how long a gestation lasts) are settings under <i>Configuration &rarr; Farm settings</i>, not fixed in the system.</p>
      </section>

      <section class="chapter" id="m-health">
        <div class="eyebrow">Module</div>
        <h2>Health</h2>
        <p class="lead">Vaccinations, treatments, deaths and everything the vet does, with protection against selling medicated animals.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Health']" /></div>
        <div class="table-wrap"><table class="kv">
          <tr><th>Screen</th><th>Use it to</th></tr>
          <tr><td>Vaccinations due</td><td>See which animals need which vaccine now or soon. Start here each morning.</td></tr>
          <tr><td>Vaccinations</td><td>Record a vaccination given (animal or group, vaccine, date, who).</td></tr>
          <tr><td>Treatments</td><td>Record a medicine given. If it has a withdrawal period the animal is flagged automatically.</td></tr>
          <tr><td>Withdrawal periods</td><td>See every animal still under withdrawal and when it clears.</td></tr>
          <tr><td>Health cases</td><td>Report a sick animal and follow it to recovery.</td></tr>
          <tr><td>Vet visits, Lab results</td><td>Log visits and test results against the animals concerned.</td></tr>
          <tr><td>Quarantine &amp; isolation</td><td>Start and end quarantine for an animal or group.</td></tr>
          <tr><td>Mortality</td><td>Record a death with its cause. Every death must be recorded.</td></tr>
          <tr><td>Culling</td><td>Cull an animal with reason, weight, health, performance and the sale or disposal value.</td></tr>
          <tr><td>Mortality analysis, Health alerts</td><td>Spot patterns by pen, batch, age, litter, sow, breed, month and stage.</td></tr>
          <tr><td>Medicines, Diseases, Vaccination schedules</td><td>The reference lists the forms draw from.</td></tr>
        </table></div>
        <x-guide.shot name="health-vaccinations-due.png" caption="Vaccinations due: what to vaccinate today and what is overdue." />
        <div class="note warn"><b>Withdrawal protection</b><p>An animal under an active medication withdrawal cannot be sold or sent to slaughter until the period ends. If you try, the system blocks it and names the date it will be clear.</p></div>
        <div class="note"><b>Deaths and culling may need approval</b><p>Disposal and corrections to mortality records are on the approval list, so a manager confirms them. See <a href="#m-tasks" style="color:var(--accent)">Tasks and alerts</a>.</p></div>
      </section>

      <section class="chapter" id="m-biosecurity">
        <div class="eyebrow">Module</div>
        <h2>Biosecurity</h2>
        <p class="lead">Keeps disease out. Know who has been on the farm and prove the routine checks were done.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Biosecurity']" /></div>
        <ul>
          <li><b>Visitor log</b>: <b>Sign in visitor</b> at the gate (who, why, which areas, last contact with pigs) and sign them out when they leave.</li>
          <li><b>Inspections</b>: <b>Record inspection</b> and tick the checklist items for the area.</li>
          <li><b>Checklist items</b>: the questions an inspection asks. Managers can add or change them.</li>
        </ul>
      </section>

      <section class="chapter" id="m-production">
        <div class="eyebrow">Module</div>
        <h2>Production</h2>
        <p class="lead">Grower and finisher pigs are managed as <b>batches</b>, so their feed, weight gain, losses and cost are followed together.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Production']" /><span>Batches &middot; Feed records &middot; Feed types &middot; Overview</span></div>
        <x-guide.shot name="production-batches.png" caption="Production batches: head count, feed given and cost for each group." />
        <ol class="steps">
          <li><b>Start a batch.</b> <x-guide.path :items="['Production','Batches']" /> &rarr; <b>Start batch</b>. Give the pen, the number of pigs and the start date.</li>
          <li><b>Record feed.</b> <x-guide.path :items="['Production','Feed records']" /> &rarr; <b>Record feed</b>. Choose the batch, the feed and the quantity. Stock in the store reduces automatically.</li>
          <li><b>Weigh in.</b> Record sample weights on the batch to see average daily gain.</li>
          <li><b>Record losses.</b> Deaths update the batch&rsquo;s head count, which is kept as a ledger you can read on the batch.</li>
        </ol>
        <p>The <b>Production overview</b> compares batches. Feed cost and conversion appear as the data builds.</p>
      </section>

      <section class="chapter" id="m-feedmill">
        <div class="eyebrow">Module</div>
        <h2>Feed mill</h2>
        <p class="lead">Turns raw materials into finished feed using a formula, and works out what it cost to make.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Feed mill']" /><span>Formulas &middot; Production orders &middot; Finished batches</span></div>
        <x-guide.shot name="feed-formulas.png" caption="Feed formulas: each lists its ingredients and their proportions." />
        <ol class="steps">
          <li><b>Formula.</b> A formula lists its ingredients and quantities. The nutritionist builds and maintains these under <i>Formulas</i>.</li>
          <li><b>Plan a production order.</b> <x-guide.path :items="['Feed mill','Production orders']" /> &rarr; <b>New production order</b>. Choose an active formula and the quantity to make (for example 1,000 kg). The system lists the raw materials needed.</li>
          <li><b>Confirm what was actually used</b> on the order, since the real use can differ from the plan.</li>
          <li><b>Complete the order.</b> The raw materials leave the store, the finished feed arrives, and the cost per kg is calculated.</li>
        </ol>
        <div class="note tip"><b>Made a mistake?</b><p>A completed run can be <b>reversed</b>: the raw materials go back and the finished feed is taken out. Nothing is lost.</p></div>
      </section>

      <section class="chapter" id="m-inventory">
        <div class="eyebrow">Module</div>
        <h2>Inventory</h2>
        <p class="lead">Everything in the stores: raw materials, feed, medicines and supplies, with batch numbers and expiry dates.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Inventory']" /></div>
        <x-guide.shot name="stock-overview.png" caption="Stock on hand by item and store, with value." />
        <div class="table-wrap"><table class="kv">
          <tr><th>Screen</th><th>Use it to</th></tr>
          <tr><td>Stock on hand</td><td>See how much of each item is in each store, and what it is worth.</td></tr>
          <tr><td>Stock alerts</td><td>Items running low or near expiry.</td></tr>
          <tr><td>Items</td><td>The catalogue of things you stock. Add a new item here first.</td></tr>
          <tr><td>Stores</td><td>The places stock is kept.</td></tr>
          <tr><td>Batches</td><td>Each received lot, with batch number and expiry.</td></tr>
          <tr><td>Stock ledger</td><td>Every movement in and out, in order. Read-only.</td></tr>
          <tr><td>Adjustments</td><td>Correct stock after damage, loss or a found difference. Needs approval.</td></tr>
          <tr><td>Stock counts</td><td>Count what is physically there and let the system post the differences.</td></tr>
        </table></div>
        <div class="note"><b>Stock changes only through documents</b><p>You do not type a new stock number. Stock rises when goods are received or feed is made, and falls when it is used, sold or adjusted. To fix a difference, use an <b>Adjustment</b> or a <b>Stock count</b>.</p></div>
      </section>

      <section class="chapter" id="m-purchasing">
        <div class="eyebrow">Module</div>
        <h2>Purchasing</h2>
        <p class="lead">Buying, from asking for something to paying the supplier.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Purchasing']" /></div>
        <x-guide.shot name="purchase-orders.png" caption="Purchase orders and how much of each has been received." />
        <ol class="steps">
          <li><b>Purchase request.</b> Anyone entitled raises a request for what is needed. A manager approves it.</li>
          <li><b>Purchase order.</b> Raise the order to a supplier from the approved request. Large orders wait for approval.</li>
          <li><b>Goods receipt.</b> When the goods arrive, receive them into a store with batch number and expiry. A part delivery marks the order <i>partly received</i>.</li>
          <li><b>Supplier invoice.</b> Record the invoice the supplier sends.</li>
          <li><b>Supplier payment.</b> Pay all or part. <b>Supplier balances</b> shows what is still owed to each supplier.</li>
        </ol>
      </section>

      <section class="chapter" id="m-semen">
        <div class="eyebrow">Module</div>
        <h2>Semen</h2>
        <p class="lead">Boar semen is collected, tested and sold under strict quality control.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Semen']" /><span>Boars &middot; Batches &middot; Semen stock &middot; Production report</span></div>
        <x-guide.shot name="semen-batches.png" caption="Semen batches and their quality-control status." />
        <ol class="steps">
          <li><b>Add the boar</b> to the semen programme under <i>Boars</i>.</li>
          <li><b>Record a collection.</b> <x-guide.path :items="['Semen','Batches']" /> &rarr; <b>Record a collection</b>: the boar, date, volume, doses made.</li>
          <li><b>Laboratory QC.</b> The laboratory records its test on the batch. The batch status moves through <i>pending QC, passed, failed, released, quarantined, expired</i> or <i>destroyed</i>.</li>
          <li><b>Release.</b> Only a batch that has passed and been released can be sold. Release needs the Semen Laboratory Manager&rsquo;s approval.</li>
        </ol>
        <div class="note warn"><b>Failed batches cannot be sold</b><p>The sales screens will not let a failed or unreleased batch onto an order or invoice.</p></div>
      </section>

      <section class="chapter" id="m-sales">
        <div class="eyebrow">Module</div>
        <h2>Sales</h2>
        <p class="lead">Customers, orders, invoices and the money coming in, whether it is cash, transfer, POS, credit or part payment.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Sales']" /><span>Customers &middot; Orders &middot; Invoices &middot; Payments received &middot; Outstanding balances</span></div>
        <x-guide.shot name="invoices.png" caption="Invoices with their status and the balance still owed." />
        <ul>
          <li><b>Credit customers</b> have a <b>credit limit</b>, agreed terms, a current balance and an overdue balance. Depending on settings, selling beyond the limit shows a warning or is blocked.</li>
          <li><b>Price lists</b> (Configuration) set the prices the order uses. A discount above the allowed threshold needs approval.</li>
          <li><b>Outstanding balances</b> lists who owes what and for how long.</li>
          <li>Customers can also pay a <b>deposit</b> before they buy.</li>
        </ul>
        <p>The full walkthrough is in <a href="#w-sell" style="color:var(--accent)">Sell and get paid</a>.</p>
      </section>

      <section class="chapter" id="m-slaughter">
        <div class="eyebrow">Module</div>
        <h2>Slaughter and meat</h2>
        <p class="lead">From a finished pig on the day of slaughter to the packs of meat in the cold room, with every step recorded.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Slaughter & meat']" /><span>Slaughter days &middot; Carcasses &middot; Meat production &middot; Meat stock &middot; Trace a product &middot; Yield report</span></div>
        <ul>
          <li><b>Slaughter day</b>: schedule the day, then add each pig received with its live weight and inspection result. A pig under withdrawal is refused.</li>
          <li><b>Carcass</b>: record the carcass weight. The system gives the <b>dressing percentage</b> (carcass weight &divide; live weight &times; 100).</li>
          <li><b>Meat production</b>: <b>Make meat from carcasses</b>, choosing products from the <i>Product catalogue</i> and the storage location. Each product keeps its source, quantity, weight, expiry, cost and status.</li>
          <li><b>Meat stock</b>: what is in storage now. <b>Yield report</b>: how well carcasses turned into saleable meat.</li>
        </ul>
        <x-guide.shot name="trace.png" caption="Trace a product: follow a pack of meat back to the pig, the litter, the semen and the feed." />
        <div class="note tip"><b>Trace a product</b><p>Enter a meat batch or a pig and the page shows its whole family tree, back to the semen batch, sow, litter, pen, feed, raw materials and supplier, and forward to the customer&rsquo;s invoice. It is the fastest answer to a food-safety question.</p></div>
      </section>

      <section class="chapter" id="m-finance">
        <div class="eyebrow">Module</div>
        <h2>Finance</h2>
        <p class="lead">The books. Most entries are created for you when you invoice, receive goods or make payments; accountants review and post them.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Finance']" /></div>
        <x-guide.shot name="finance-trial-balance.png" caption="The trial balance: debits and credits must agree." />
        <div class="table-wrap"><table class="kv">
          <tr><th>Screen</th><th>Use it to</th></tr>
          <tr><td>Journal</td><td>See journal entries. <b>Post sales, receipts and purchases</b> turns the period&rsquo;s documents into entries. Manual entries are possible, and large ones need approval.</td></tr>
          <tr><td>Chart of accounts</td><td>The list of accounts.</td></tr>
          <tr><td>Cash transactions</td><td>Record money in or out of cash and bank.</td></tr>
          <tr><td>Expenses</td><td>Record a farm expense.</td></tr>
          <tr><td>Cost centres</td><td>Group costs by area, such as breeding or feed mill.</td></tr>
          <tr><td>Budgets, Budget vs actual</td><td>Plan the year and compare it with real figures.</td></tr>
          <tr><td>Trial balance, Cash flow, Profitability, Unit costs</td><td>The standard reports.</td></tr>
          <tr><td>Receivables &amp; payables</td><td>What customers owe you and what you owe suppliers.</td></tr>
        </table></div>
      </section>

      <section class="chapter" id="m-tasks">
        <div class="eyebrow">Module</div>
        <h2>Tasks and alerts</h2>
        <p class="lead">Your to-do list and the system&rsquo;s way of asking for a decision.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Tasks & alerts']" /><span>Tasks &middot; Alerts &middot; Approvals &middot; Mobile sync log</span></div>
        <x-guide.shot name="approvals.png" caption="Approvals: items waiting for a manager's decision." />
        <ul>
          <li><b>Tasks</b>: jobs assigned to you or your team. Open one, do the work, mark it done.</li>
          <li><b>Alerts</b>: things the system noticed, such as low stock, overdue vaccinations or a long-running withdrawal.</li>
          <li><b>Approvals</b>: items waiting for your decision. Open the item, read the details and choose <b>Approve</b> or <b>Reject</b>.</li>
          <li><b>Mobile sync log</b>: what the phones sent, and which entries were accepted, rejected or conflicted.</li>
        </ul>
        <h3>What needs approval</h3>
        <p>Animal disposal, mortality corrections, stock adjustments, purchase orders, customer credit, discounts above the threshold, semen batch release, slaughter adjustments, financial journals, and large payments. The thresholds are settings that management can change.</p>
        <div class="note"><b>You cannot approve your own request</b><p>The person who asks and the person who approves must be different, and the approver needs the right to approve.</p></div>
      </section>

      <section class="chapter" id="m-management">
        <div class="eyebrow">Module</div>
        <h2>Management</h2>
        <p class="lead">For owners and managers: how the business is doing against plan.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Management']" /><span>Dashboards &middot; Target vs actual &middot; KPI targets &middot; Monthly report</span></div>
        <x-guide.shot name="dashboards.png" caption="Management dashboards: the farm's main figures at a glance." />
        <ul>
          <li><b>Dashboards</b>: live figures across production, stock, sales and money.</li>
          <li><b>KPI targets</b>: the targets you set (production, semen prices, feed and so on). They are stored as data, so they are changed here, not in code.</li>
          <li><b>Target vs actual</b>: each target against what really happened.</li>
          <li><b>Monthly report</b>: the month in one printable report.</li>
        </ul>
      </section>

      <section class="chapter" id="m-website">
        <div class="eyebrow">Module</div>
        <h2>Website</h2>
        <p class="lead">Controls what visitors to the public website can see and collects their questions.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Website']" /></div>
        <ul>
          <li><b>Listings</b>: the pigs, semen or meat shown for sale. Edit the text and switch a listing on or off.</li>
          <li><b>Enquiries</b>: messages sent through the contact form. Follow each one up and mark it handled.</li>
        </ul>
      </section>

      <section class="chapter" id="m-admin">
        <div class="eyebrow">Module</div>
        <h2>Set-up and administration</h2>
        <p class="lead">Normally used by managers and the system administrator, not for day-to-day entry.</p>
        <div class="where"><span class="k">Where</span><x-guide.path :items="['Master data']" /><x-guide.path :items="['Configuration']" /><x-guide.path :items="['Administration']" /></div>
        <div class="table-wrap"><table class="kv">
          <tr><th>Group</th><th>What is in it</th></tr>
          <tr><td>Master data</td><td>Breeds, genetic lines, units of measure and the lookup lists used in drop-downs.</td></tr>
          <tr><td>Configuration</td><td><b>Farm settings</b> (gestation length and other durations, alert thresholds, approval limits, the date books are closed through) and <b>Price lists</b>.</td></tr>
          <tr><td>Administration</td><td><b>Users</b>, <b>Roles</b>, the <b>Audit log</b>, <b>Login activity</b>, <b>Data imports</b> for loading historical records, and <b>Backups &amp; monitoring</b>.</td></tr>
        </table></div>
        <h3>Adding a person</h3>
        <ol class="steps">
          <li><x-guide.path :items="['Administration','Users']" /> &rarr; <b>New user</b>.</li>
          <li>Enter their name and work email, choose a role and set the account active.</li>
          <li>Tell them to sign in and change their password. Roles that need two-factor will set it up at the first sign-in.</li>
          <li>When someone leaves, switch the account to inactive. It is never deleted, so the history stays intact.</li>
        </ol>
        <div class="note warn"><b>Go-live data reset</b><p>The Owner has a screen that wipes the demo data before the farm goes live. It is for launch only and should not be used on a farm in daily use.</p></div>
        <div class="note tip"><b>Backups &amp; monitoring</b><p>Owners and general managers should look at this page each morning. It shows the last backup, whether it could be restored, and anything that needs fixing. Everything should read OK.</p></div>
      </section>

      {{-- ============================================================ WALKTHROUGHS --}}
      <div class="group-title">Step-by-step jobs</div>

      <section class="chapter" id="w-feed">
        <div class="eyebrow">Walkthrough</div>
        <h2>Buy raw materials and make feed</h2>
        <p class="lead">Roles: Store Officer, Feed Mill Manager. About ten minutes of data entry.</p>
        <ol class="steps">
          <li><b>Raise a purchase request</b> for the maize you need. <x-guide.path :items="['Purchasing','Purchase requests']" /></li>
          <li><b>Approve it</b> (a manager), then <b>raise the purchase order</b> to the supplier and get it approved.</li>
          <li><b>Receive the goods</b> into the main store: <x-guide.path :items="['Purchasing','Goods receipts']" />. Enter the batch number and expiry. If only part arrived, receive part.</li>
          <li><b>Record the supplier&rsquo;s invoice</b> and, when you pay, the <b>supplier payment</b>.</li>
          <li><b>Check stock.</b> <x-guide.path :items="['Inventory','Stock on hand']" /> now shows the quantity and cost received.</li>
          <li><b>Plan feed.</b> <x-guide.path :items="['Feed mill','Production orders']" /> &rarr; <b>New production order</b>, choose the formula and quantity.</li>
          <li><b>Confirm actual use</b> and <b>complete</b> the order. Raw materials leave the store, finished feed arrives, and the cost per kg is set.</li>
        </ol>
        <div class="note tip"><b>You will know it worked when</b><p>The purchase order says <i>received</i>, supplier balances show what is still unpaid, and the finished feed batch has a cost per kg.</p></div>
      </section>

      <section class="chapter" id="w-pig">
        <div class="eyebrow">Walkthrough</div>
        <h2>Follow a pig from service to weaning</h2>
        <p class="lead">Roles: Breeding Manager, Veterinarian, Farm Worker.</p>
        <ol class="steps">
          <li><b>Register the sow and the boar</b> under <x-guide.path :items="['Animals']" /> if they are not already there.</li>
          <li><b>Record the service</b> on the sow with the boar or semen batch. Note the expected dates it shows.</li>
          <li><b>Confirm pregnancy</b> on the check date.</li>
          <li><b>Record farrowing</b>. A litter is created. Add born alive, stillborn and birth weights.</li>
          <li><b>Look after the piglets</b>: record weights, move them to a pen, vaccinate, treat. If a treatment has a withdrawal period the animal is flagged until it clears.</li>
          <li><b>Wean</b> the litter and enter the weaning count and weights.</li>
          <li><b>Check the result.</b> The litter page shows mortality and weaning percentages and average weights; the sow&rsquo;s page shows her performance.</li>
        </ol>
      </section>

      <section class="chapter" id="w-sell">
        <div class="eyebrow">Walkthrough</div>
        <h2>Sell and get paid</h2>
        <p class="lead">Roles: Sales Officer, Accountant.</p>
        <ol class="steps">
          <li><b>Create the customer</b> if new: <x-guide.path :items="['Sales','Customers']" />. For credit customers set a credit limit and terms.</li>
          <li><b>Create an order</b>: <x-guide.path :items="['Sales','Orders']" /> &rarr; <b>New order</b>. Add lines (semen doses from a released batch, a finishing pig, meat). Prices come from the price list.</li>
          <li><b>Confirm</b> the order, then <b>dispatch</b> it. Stock reduces now.</li>
          <li><b>Invoice.</b> The invoice shows lines, totals, and amount due.</li>
          <li><b>Receive payment.</b> <x-guide.path :items="['Sales','Payments received']" /> &rarr; <b>Receive payment</b>. Pick the customer, amount and method; apply it to the invoice. Part payments leave the rest owing.</li>
          <li><b>Check the balance.</b> <x-guide.path :items="['Sales','Outstanding balances']" /> and the customer page show what is still due.</li>
        </ol>
        <div class="note"><b>If something stops you</b><p>Selling over the credit limit, a failed-QC semen batch, or a pig under withdrawal is warned about or blocked. Read the message; fix the cause or get an approval.</p></div>
        <div class="note tip"><b>Wrong receipt?</b><p>Void the receipt. The customer&rsquo;s balance goes back to what it was, and the voided record stays visible.</p></div>
      </section>

      <section class="chapter" id="w-meat">
        <div class="eyebrow">Walkthrough</div>
        <h2>From live pig to meat on the invoice</h2>
        <p class="lead">Roles: Slaughter Manager, Sales Officer.</p>
        <ol class="steps">
          <li><b>Schedule a slaughter day</b> and add each pig received, with live weight and inspection.</li>
          <li><b>Record each carcass</b> and its weight. Note the dressing percentage.</li>
          <li><b>Make meat</b> from the carcasses: choose the cuts, quantities and the cold store.</li>
          <li><b>Sell</b> part of it to a customer as in <a href="#w-sell" style="color:var(--accent)">Sell and get paid</a>.</li>
          <li><b>Trace it.</b> Open <x-guide.path :items="['Slaughter & meat','Trace a product']" /> on the meat batch or the pig and follow the chain both ways.</li>
        </ol>
      </section>

      <section class="chapter" id="w-month">
        <div class="eyebrow">Walkthrough</div>
        <h2>Month-end</h2>
        <p class="lead">Role: Accountant, with the Owner or General Manager reviewing.</p>
        <ol class="steps">
          <li>Make sure the month&rsquo;s receipts, invoices, goods received and payments have all been entered and approvals cleared.</li>
          <li><b>Post the documents.</b> <x-guide.path :items="['Finance','Journal']" /> &rarr; <b>Post sales, receipts and purchases</b>.</li>
          <li><b>Review the trial balance</b> and the receivables and payables.</li>
          <li><b>Check the other reports</b>: profitability, cash flow, budget vs actual.</li>
          <li><b>Close the period</b> by setting the <i>books closed through</i> date under <x-guide.path :items="['Configuration','Farm settings']" /> once everything agrees.</li>
          <li>Print or export the <b>Monthly report</b> from the Management group.</li>
        </ol>
        <div class="note warn"><b>After closing</b><p>Records dated in a closed period cannot be changed. Do the checks before you close.</p></div>
      </section>

      {{-- ============================================================ MOBILE --}}
      <div class="group-title">On the phone</div>

      <section class="chapter" id="mobile">
        <div class="eyebrow">Mobile</div>
        <h2>The mobile app</h2>
        <p class="lead">For people in the pens. It does the everyday jobs quickly and works with no signal.</p>
        <ul>
          <li><b>Sign in</b> once on the phone with your ERP email and password. You stay signed in for about 30 days.</li>
          <li><b>Who can use it</b>: roles with mobile access (see the table in <a href="#roles" style="color:var(--accent)">Who can see what</a>). Roles that need two-factor use the web ERP instead.</li>
          <li><b>Scan</b> an ear tag or QR code to open the animal, pen, batch or task.</li>
          <li><b>Quick actions</b> record the common jobs: weights, feed, deaths, movements, treatments and similar. You see only what your permissions allow.</li>
        </ul>
        <h3>Working offline</h3>
        <p>When there is no signal the app saves what you do on the phone and sends it when the connection returns. Each entry carries the time it really happened. Sending twice never creates a duplicate.</p>
        <div class="table-wrap"><table class="kv">
          <tr><th>Result</th><th>What it means</th><th>What to do</th></tr>
          <tr><td>Accepted</td><td>Saved in the ERP.</td><td>Nothing.</td></tr>
          <tr><td>Rejected</td><td>Something in the entry is wrong or not allowed.</td><td>Read the message, correct it and enter it again.</td></tr>
          <tr><td>Conflict</td><td>The farm moved on: for example the pig was sold meanwhile. Nothing was applied.</td><td>Tell your supervisor. They review it under <i>Tasks &amp; alerts &rarr; Mobile sync log</i>.</td></tr>
          <tr><td>Failed</td><td>A temporary problem on the server. Nothing was saved.</td><td>The app keeps it and tries again later.</td></tr>
        </table></div>
      </section>

      {{-- ============================================================ HELP --}}
      <div class="group-title">Help</div>

      <section class="chapter" id="glossary">
        <div class="eyebrow">Reference</div>
        <h2>Glossary</h2>
        <div class="table-wrap"><table class="kv">
          <tr><th>Term</th><th>Meaning</th></tr>
          <tr><td>Batch</td><td>A group handled together: a production batch of pigs, a lot of goods received, a semen collection or a run of feed.</td></tr>
          <tr><td>Cull</td><td>Remove an animal from the herd for a recorded reason, with its value.</td></tr>
          <tr><td>Dressing percentage</td><td>Carcass weight divided by live weight, times 100.</td></tr>
          <tr><td>Farrowing</td><td>A sow giving birth. It creates a litter record.</td></tr>
          <tr><td>Goods receipt</td><td>The record that ordered goods have arrived in a store.</td></tr>
          <tr><td>Ledger</td><td>A list of entries that is only ever added to. Stock and the accounts are ledgers.</td></tr>
          <tr><td>Litter</td><td>The piglets born to one sow at one farrowing.</td></tr>
          <tr><td>Mutation</td><td>One saved action from the mobile app, waiting to be sent or already sent.</td></tr>
          <tr><td>QC</td><td>Quality control. Semen must pass it before sale.</td></tr>
          <tr><td>Reverse / void</td><td>Cancel a record by adding an opposite one, keeping both in the history.</td></tr>
          <tr><td>Trial balance</td><td>A list of all accounts showing debits and credits agree.</td></tr>
          <tr><td>Weaning</td><td>Taking piglets off the sow&rsquo;s milk, usually moving them to the nursery.</td></tr>
          <tr><td>Withdrawal period</td><td>The days after a medicine during which an animal must not be sold or slaughtered.</td></tr>
        </table></div>
      </section>

      <section class="chapter" id="faq">
        <div class="eyebrow">Reference</div>
        <h2>Questions and fixes</h2>

        <details class="faq"><summary>I cannot see a menu or button my colleague has.</summary><div><p>Access depends on your role. Ask the administrator to check your role under <i>Administration &rarr; Roles</i>.</p></div></details>
        <details class="faq"><summary>I lost my phone and cannot get a two-factor code.</summary><div><p>Use one of the recovery codes you saved when you set up two-factor. If you have none, an administrator must reset your two-factor sign-in.</p></div></details>
        <details class="faq"><summary>I entered something wrong. How do I delete it?</summary><div><p>Records that affect stock or money are not deleted. Use the record&rsquo;s <b>Cancel</b>, <b>Void</b> or <b>Reverse</b> action, then enter it correctly. See <a href="#forms" style="color:var(--accent)">Saving and correcting records</a>.</p></div></details>
        <details class="faq"><summary>The system will not let me sell a pig.</summary><div><p>The pig is probably under a medication withdrawal period. Open <i>Health &rarr; Withdrawal periods</i> to see when it ends. Other causes: the pig is no longer active, or the customer is over their credit limit.</p></div></details>
        <details class="faq"><summary>Semen will not appear on an order.</summary><div><p>Only batches that have passed QC and been released can be sold. Check the batch status under <i>Semen &rarr; Batches</i>.</p></div></details>
        <details class="faq"><summary>Stock on hand does not match the store.</summary><div><p>Do not edit it. Count the store with <i>Inventory &rarr; Stock counts</i> or raise an <i>Adjustment</i>. A manager approves the difference.</p></div></details>
        <details class="faq"><summary>My request has been waiting a long time.</summary><div><p>It is probably waiting in <i>Tasks &amp; alerts &rarr; Approvals</i> for a manager. Remind them. The person who requested it cannot approve it.</p></div></details>
        <details class="faq"><summary>A phone entry shows as a conflict.</summary><div><p>The farm changed after the phone last synced, so the system refused to apply it. A supervisor looks at it in the Mobile sync log and enters what is correct.</p></div></details>
        <details class="faq"><summary>The page looks wrong or will not load.</summary><div><p>Refresh the page. If it is still wrong, sign out and in again. If it keeps happening, tell your administrator what you were doing and the time; this lets them find the error.</p></div></details>

        <div class="note tip"><b>Still stuck?</b><p>Tell your supervisor or system administrator the screen you were on, what you pressed and the message you saw. A screenshot helps.</p></div>
      </section>

      <footer>Azana Farms ERP &middot; User guide</footer>
    </div>
  </main>
</div>
<button class="back-top" id="backTop" aria-label="Back to top"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 13V3M3.5 7.5 8 3l4.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
<dialog class="lightbox" id="lightbox" aria-label="Screenshot"><img alt=""></dialog>
<script src="{{ asset('js/api-docs.js') }}?v={{ @filemtime(public_path('js/api-docs.js')) }}"></script>
<script src="{{ asset('js/user-guide.js') }}?v={{ @filemtime(public_path('js/user-guide.js')) }}"></script>
</body>
</html>
