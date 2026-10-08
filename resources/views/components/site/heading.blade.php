@props(['eyebrow' => null, 'title', 'intro' => null, 'id' => null, 'center' => false, 'level' => 2])
<div {{ $attributes->class(['heading', 'center' => $center, 'reveal']) }}>
    @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
    <h{{ $level }} @if ($id) id="{{ $id }}-h" @endif>{{ $title }}</h{{ $level }}>
    @if ($intro)<p class="intro">{{ $intro }}</p>@endif
</div>
