@props(['href', 'variant' => 'primary', 'small' => false])
<a href="{{ $href }}" {{ $attributes->class(['btn', "btn-{$variant}", 'btn-sm' => $small]) }}>{{ $slot }}</a>
