@props(['name', 'alt' => '', 'ratio' => 'r43', 'eager' => false])
@php
    // The farm's own photograph if it has been added (see config/website.php), otherwise a plain brand panel. Never a stock picture.
    $file = collect(['jpg', 'jpeg', 'webp', 'png'])->map(fn ($ext) => "images/site/{$name}.{$ext}")->first(fn ($path) => file_exists(public_path($path)));
    $size = $file ? @getimagesize(public_path($file)) : null;
@endphp
<div {{ $attributes->class(['photo', $ratio]) }}>
    @if ($file)
        <img src="{{ asset($file) }}?v={{ filemtime(public_path($file)) }}" alt="{{ $alt }}" @if ($size) width="{{ $size[0] }}" height="{{ $size[1] }}" @endif
             @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async">
    @else
        <div class="ph" aria-hidden="true"></div>
    @endif
</div>
