@props(['eyebrow' => null, 'title', 'intro' => null, 'crumbs' => []])
<header class="page-head">
    <div class="wrap">
        @if ($crumbs)
            <nav aria-label="Breadcrumb" class="crumbs">
                @foreach ($crumbs as $label => $url)<a href="{{ $url }}">{{ $label }}</a> / @endforeach<span aria-current="page">{{ $title }}</span>
            </nav>
        @endif
        <div class="hero-in">
            @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
            <h1>{{ $title }}</h1>
            @if ($intro)<p class="intro">{{ $intro }}</p>@endif
        </div>
    </div>
</header>
