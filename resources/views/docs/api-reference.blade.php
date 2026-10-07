@php
    use App\Support\ApiDocs\ApiReference;
    use App\Support\ApiDocs\Highlight;

    $anchor = fn (array $e) => ApiReference::anchor($e);
    $methodClass = fn (string $m) => strtolower($m);
    $methodShort = fn (string $m) => $m === 'DELETE' ? 'DEL' : $m;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Azana Farms API Reference</title>
<meta name="description" content="Reference documentation for the Azana Farms mobile API v1: sign-in, lookups, the twelve quick actions and offline sync.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Manrope:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/api-docs.css') }}?v={{ @filemtime(public_path('css/api-docs.css')) }}">
</head>
<body>

<a class="skip-link" href="#main-content">Skip to content</a>
<div class="shell">
  <header class="topbar">
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 4.5h14M2 9h14M2 13.5h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </button>
    <div class="mark" aria-hidden="true"></div>
    <span class="name">Azana Farms API</span>
    <span class="tag">v1</span>
    <div class="spacer"></div>
    <span class="base-url">{{ $base }}</span>
    <button class="theme-toggle" id="themeToggle" aria-label="Switch between light and dark">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13.5 9.5A5.5 5.5 0 0 1 6.5 2.5a5.5 5.5 0 1 0 7 7Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
    </button>
  </header>

  <div class="scrim" id="scrim"></div>

  <nav class="sidenav" id="sidenav" aria-label="API sections">
    <div class="search-wrap">
      <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true"><circle cx="6.3" cy="6.3" r="4.5" stroke="currentColor" stroke-width="1.4"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
      <input id="navSearch" type="search" placeholder="Find an endpoint…" aria-label="Filter endpoints">
    </div>
    <div id="navList">
      <div class="nav-group" data-group="intro">
        <div class="nav-group-title">Getting Started</div>
        @foreach ([['intro', 'Introduction', 'introduction overview mobile app'], ['auth', 'Authentication', 'authentication bearer token sanctum sign in'], ['conventions', 'Requests and responses', 'json format errors validation'], ['offline', 'Offline and idempotency', 'offline sync idempotency client_id queue retry'], ['outcomes', 'Mutation outcomes', 'accepted rejected conflict failed status'], ['ratelimits', 'Rate limits', 'rate limits throttle 429'], ['errors', 'Errors', 'errors status codes']] as [$id, $label, $words])
          <a class="nav-link" data-search="{{ $words }}" href="#{{ $id }}"><span class="m"></span><span class="p">{{ $label }}</span></a>
        @endforeach
      </div>
      @foreach ($groups as $group)
        <div class="nav-group" data-group="{{ $group['key'] }}">
          <div class="nav-group-title">{{ $group['title'] }}</div>
          @foreach ($group['endpoints'] as $e)
            <a class="nav-link" data-search="{{ strtolower($e['method'].' '.$e['path'].' '.strip_tags($e['summary']).' '.($e['quick'] ?? '').' '.str_replace('_', ' ', $e['quick'] ?? '')) }}" href="#{{ $anchor($e) }}"><span class="m {{ $methodClass($e['method']) }}">{{ $methodShort($e['method']) }}</span><span class="p">{{ $e['path'] }}</span></a>
          @endforeach
        </div>
      @endforeach
    </div>
    <p class="nav-empty" id="navEmpty">No endpoints match.</p>
  </nav>

  <main id="main-content">
    <div class="content-inner">

      <section class="hero" id="intro">
        <div class="eyebrow">API REFERENCE &middot; V1</div>
        <h1>Build the Azana Farms mobile app</h1>
        <p>Everything the field app needs to sign a worker in, scan an animal tag, record the twelve everyday jobs even without a connection, and send them safely later &mdash; every endpoint here is checked against the running API.</p>
      </section>

      <div class="intro-grid">
        <div class="intro-block" id="auth">
          <h3>Authentication</h3>
          <p>Sign in once per device with <code>POST /auth/login</code>. It returns a token; send it on every other request. A token lasts 30 days, then the worker signs in again.</p>
          <div class="code-block" style="margin-top:.7rem;">
            <div class="code-head"><span>Header</span></div>
            <pre><span class="tok-key">Authorization:</span> Bearer &lt;token&gt;
<span class="tok-key">Accept:</span> application/json</pre>
          </div>
          <p style="margin-top:.6rem;">Access is checked on <b style="color:var(--text)">every</b> request: the worker must still be active and still hold the <code>mobile.view</code> permission. What they may <i>do</i> is their ordinary role permissions, the same as on the web.</p>
          <div class="note"><b>Two-factor roles</b>Roles that must use two-factor sign-in (accountant, owner) cannot sign in to the mobile API. They get <code>403 two_factor_required</code>.</div>
        </div>

        <div class="intro-block" id="conventions">
          <h3>Requests and responses</h3>
          <p>JSON in, JSON out. Send <code>Accept: application/json</code> so errors come back as JSON. Dates are ISO 8601 with a time zone; quantities are numbers (weights in kg, money in the farm currency's minor units where it appears).</p>
          <p>Animals, batches, litters and tasks are named by their <b style="color:var(--text)">codes</b> (what is printed on the tag or screen); everything else (pens, medicines, feed types...) by the <code>id</code> from <code>GET /reference</code>.</p>
          <div class="code-block" style="margin-top:.7rem;">
            <div class="code-head"><span>Validation error (422)</span></div>
            <pre>{
  <span class="tok-key">"message"</span>: <span class="tok-str">"The email field is required."</span>,
  <span class="tok-key">"errors"</span>: { <span class="tok-key">"email"</span>: [<span class="tok-str">"The email field is required."</span>] }
}</pre>
          </div>
          <div class="code-block" style="margin-top:.6rem;">
            <div class="code-head"><span>Refusal (401 / 403 / 404)</span></div>
            <pre>{
  <span class="tok-key">"message"</span>: <span class="tok-str">"You are not allowed to use the mobile app."</span>,
  <span class="tok-key">"code"</span>: <span class="tok-str">"mobile_forbidden"</span>
}</pre>
          </div>
        </div>

        <div class="intro-block" id="offline">
          <h3>Offline and idempotency</h3>
          <p>The app saves what the worker does on the phone and sends it when it can. Each saved item is a <b style="color:var(--text)">mutation</b> with a <code>client_id</code> (a UUID the phone makes at that moment).</p>
          <ul>
            <li>Send the same mutation as often as you like. The server runs it <b style="color:var(--text)">once</b>; every later time it returns the stored outcome with <code>replayed: true</code>.</li>
            <li>Send a queue oldest first, up to 100 at a time. Each mutation succeeds or fails on its own.</li>
            <li>Give each mutation the time it <i>happened</i> in <code>occurred_at</code>; the server records the event with that time, not the time it arrived.</li>
            <li>Keep the reference lists from <code>GET /reference</code> on the phone so forms work offline; refresh them when online.</li>
          </ul>
          <div class="code-block" style="margin-top:.7rem;">
            <div class="code-head"><span>A mutation</span></div>
            <pre>{
  <span class="tok-key">"client_id"</span>: <span class="tok-str">"6c9e2a42-9f0e-4d4f-b0c1-0a1f6a6f2c11"</span>,
  <span class="tok-key">"type"</span>: <span class="tok-str">"record_weight"</span>,
  <span class="tok-key">"occurred_at"</span>: <span class="tok-str">"2026-10-08T06:42:10+01:00"</span>,
  <span class="tok-key">"payload"</span>: { <span class="tok-key">"animal"</span>: <span class="tok-str">"SOW-000123"</span>, <span class="tok-key">"weight_kg"</span>: <span class="tok-num">182.5</span> }
}</pre>
          </div>
        </div>

        <div class="intro-block" id="outcomes">
          <h3>Mutation outcomes</h3>
          <p>Every mutation gets exactly one of four outcomes. What the app does next depends on which:</p>
          <table class="kv">
            <tr><th>status</th><th>Meaning</th><th>The app should</th></tr>
            <tr><td class="mono">accepted</td><td>Done. <code>server_type</code> and <code>server_id</code> say what was created.</td><td>Remove it from the queue.</td></tr>
            <tr><td class="mono">rejected</td><td>The request itself is wrong: unknown type, not allowed, invalid fields (<code>error.fields</code>), or a business rule the data breaks. Sending it again will not help.</td><td>Show the worker the message; let them correct it and send a <i>new</i> mutation.</td></tr>
            <tr><td class="mono">conflict</td><td>The farm has moved on since the phone saw it: the pig is already dead, the litter already weaned, the stock changed. <b>Nothing was applied</b>; a supervisor sees it in the web app.</td><td>Show the worker. Do not resend.</td></tr>
            <tr><td class="mono">failed</td><td>An unexpected server error. Nothing was saved.</td><td>Keep it in the queue and send the same mutation again later.</td></tr>
          </table>
        </div>

        <div class="intro-block" id="ratelimits">
          <h3>Rate limits</h3>
          <p>Exceeding a limit returns <code>429</code> with a <code>Retry-After</code> header.</p>
          <table class="kv">
            <tr><th>Limiter</th><th>Limit</th><th>Applies to</th></tr>
            <tr><td class="mono">mobile-login</td><td class="num">5&nbsp;/&nbsp;min</td><td>Sign-in, per email (whatever the address)</td></tr>
            <tr><td class="mono">mobile-login</td><td class="num">20&nbsp;/&nbsp;min</td><td>Sign-in, per address (whatever the email)</td></tr>
            <tr><td class="mono">mobile</td><td class="num">240&nbsp;/&nbsp;min</td><td>Every other request, per user</td></tr>
          </table>
        </div>
      </div>

      <div class="intro-grid" style="grid-template-columns:1fr; border-bottom:none; margin-bottom:1.5rem;" id="errors">
        <div class="intro-block">
          <h3>Errors</h3>
          <table class="kv">
            <tr><th>Code</th><th>Meaning</th></tr>
            <tr><td class="mono num">401</td><td>Missing, wrong or expired token, or wrong credentials at sign-in.</td></tr>
            <tr><td class="mono num">403</td><td>The account is inactive, lacks the mobile permission, the token is not a mobile token, or the role needs the web app. <code>code</code> says which.</td></tr>
            <tr><td class="mono num">404</td><td>A scanned code or id matches nothing the user may see.</td></tr>
            <tr><td class="mono num">409</td><td>A single <code>/quick/{type}</code> mutation was a conflict.</td></tr>
            <tr><td class="mono num">422</td><td>The request is invalid, or a single quick mutation was rejected.</td></tr>
            <tr><td class="mono num">429</td><td>Rate limit exceeded.</td></tr>
            <tr><td class="mono num">503</td><td>A single quick mutation failed on the server: send it again.</td></tr>
          </table>
        </div>
      </div>

      @foreach ($groups as $group)
        <section class="group-head" id="{{ $group['key'] }}"><h2>{{ $group['title'] }}</h2><p>{{ $group['intro'] }}</p></section>

        @foreach ($group['endpoints'] as $e)
          <section class="endpoint" id="{{ $anchor($e) }}">
            <div class="endpoint-head">
              <span class="method {{ $methodClass($e['method']) }}">{{ $e['method'] }}</span><code class="path">{{ $e['path'] }}</code>
              @if (! ($e['auth'] ?? true))<span class="noauth">no token</span>@endif
              @if (! empty($e['permission']))<span class="perm">{{ $e['permission'] }}</span>@endif
            </div>
            <p class="desc">{!! $e['summary'] !!}</p>
            <div class="meta-line"><span><b>Rate limit</b> {{ $e['limit'] ?? 'mobile' }}</span></div>
            <div class="endpoint-grid">
              <div class="endpoint-docs">
                @if (! empty($e['params']))
                  <h4>{{ $e['params']['title'] }}</h4>
                  <table class="params">
                    <thead><tr><th>Field</th><th>Type</th><th>Notes</th></tr></thead>
                    <tbody>
                      @foreach ($e['params']['rows'] as [$name, $type, $required, $note])
                        <tr>
                          <td class="field">{{ $name }}@if ($required)<span class="req">required</span>@else<span class="opt">optional</span>@endif</td>
                          <td class="rule">{{ $type }}</td>
                          <td class="desc">{!! $note !!}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                @endif
                @foreach ($e['notes'] ?? [] as [$title, $text])
                  <div class="note"><b>{{ $title }}</b>{!! $text !!}</div>
                @endforeach
                <div class="status-row">
                  @foreach ($e['statuses'] ?? [] as [$code, $text, $kind])
                    <span class="status-pill {{ $kind }}">{{ $code }} {{ $text }}</span>
                  @endforeach
                </div>
              </div>
              <div class="endpoint-code">
                @php($curl = Highlight::curlText($e + ['auth' => $e['auth'] ?? true], $base))
                <div class="code-block">
                  <div class="code-head"><span>cURL</span><button class="copy-btn" data-copy>Copy</button></div>
                  <pre data-raw="{{ $curl }}">{!! Highlight::curl($curl) !!}</pre>
                </div>
                @foreach ($e['responses'] ?? [] as [$label, $json])
                  <div class="code-block">
                    <div class="code-head"><span>{{ $label }}</span><button class="copy-btn" data-copy>Copy</button></div>
                    <pre data-raw="{{ Highlight::jsonText($json) }}">{!! Highlight::json($json) !!}</pre>
                  </div>
                @endforeach
              </div>
            </div>
          </section>
        @endforeach
      @endforeach

      <footer>Azana Farms ERP &middot; mobile API v1</footer>
    </div>
  </main>
</div>
<script src="{{ asset('js/api-docs.js') }}?v={{ @filemtime(public_path('js/api-docs.js')) }}"></script>
</body>
</html>
