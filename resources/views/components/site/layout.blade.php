@props(['site', 'title', 'description', 'canonical' => null, 'schema' => null, 'current' => null, 'livewire' => false])
@php
    $url = $canonical ?? url()->current();
    $fullTitle = $title === $site['name'] ? $title : "{$title} | {$site['name']}";
    $logo = asset('images/branding/logo.jpeg');
    $nav = ['site.home' => 'Home', 'site.about' => 'About', 'site.operations' => 'Operations', 'site.products' => 'Products', 'site.sustainability' => 'Sustainability', 'site.contact' => 'Contact'];
    $subsidiary = config('website.subsidiary_of');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $url }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $site['name'] }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:image" content="{{ $logo }}">
    <meta name="twitter:card" content="summary">
    <meta name="theme-color" content="#261B14">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
    <script>document.documentElement.classList.add('js')</script>
    @if ($schema)
        <script type="application/ld+json">{!! json_encode(array_filter($schema, fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
    @if ($livewire) @livewireStyles @endif
</head>
<body>
<a class="skip" href="#main">Skip to the content</a>

<header class="top">
    <div class="wrap bar">
        <a class="brand" href="{{ route('site.home') }}">
            <img src="{{ $logo }}" alt="" width="56" height="56">
            <span class="brand-name">Integrated Princess<br>Azana Farms</span>
            <span class="sr">{{ $site['name'] }}, home</span>
        </a>
        <nav aria-label="Main">
            <ul class="menu">
                @foreach ($nav as $route => $label)
                    <li><a href="{{ route($route) }}" @if ($current === $route) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        <x-site.button :href="route('site.contact').'#enquiry'" small class="desk-cta">Get in Touch</x-site.button>
        <button type="button" class="burger" aria-expanded="false" aria-controls="drawer" aria-label="Open menu">
            <x-site.icon name="menu" class="i-open" /><x-site.icon name="x" class="i-close" />
        </button>
    </div>
    <nav id="drawer" class="drawer" aria-label="Mobile">
        <div class="wrap">
            <ul>
                @foreach ($nav as $route => $label)
                    <li><a href="{{ route($route) }}" @if ($current === $route) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
            <x-site.button :href="route('site.contact').'#enquiry'">Get in Touch</x-site.button>
        </div>
    </nav>
</header>

<main id="main" tabindex="-1">
    {{ $slot }}
</main>

<footer class="foot">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <a class="foot-logo" href="{{ route('site.home') }}"><img src="{{ $logo }}" alt="{{ $site['name'] }}" width="104" height="104" loading="lazy"></a>
                <p class="about">{{ $site['tagline'] ?: 'Modern livestock farming, quality pigs and fresh pork.' }}</p>
            </div>
            <div>
                <p class="foot-h">Explore</p>
                <ul>
                    @foreach ($nav as $route => $label)<li><a href="{{ route($route) }}">{{ $label }}</a></li>@endforeach
                </ul>
            </div>
            <div>
                <p class="foot-h">Contact</p>
                <ul>
                    @if ($site['address'])<li>{!! nl2br(e($site['address'])) !!}</li>@endif
                    @if ($site['phone'])<li><a href="tel:{{ preg_replace('/[^\d+]/', '', $site['phone']) }}">{{ $site['phone'] }}</a></li>@endif
                    @if ($site['email'])<li><a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a></li>@endif
                    @if ($site['whatsapp'])<li><a href="https://wa.me/{{ $site['whatsapp'] }}" rel="noopener">WhatsApp</a></li>@endif
                    <li><a href="{{ route('site.contact') }}#enquiry">Send an enquiry</a></li>
                </ul>
            </div>
        </div>
        <div class="legal">
            <span>&copy; {{ now()->year }} {{ $site['legal_name'] ?: $site['name'] }}. All rights reserved.</span>
            @if ($subsidiary)<span>A subsidiary of {{ $subsidiary }}</span>@endif
        </div>
    </div>
</footer>
<script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
@if ($livewire) @livewireScripts @endif
</body>
</html>
