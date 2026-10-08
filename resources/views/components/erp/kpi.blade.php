@props(['label', 'value', 'hint' => null, 'primary' => false, 'tone' => null, 'url' => null])

{{-- One figure with its label: the building block for dashboards and report summaries. $tone (danger|warning|success) tints the figure. --}}
@php
    $toneClass = match ($tone) {
        'danger' => 'text-danger-600 dark:text-danger-400',
        'warning' => 'text-warning-600 dark:text-warning-400',
        'success' => 'text-success-600 dark:text-success-400',
        default => '',
    };
    $tag = $url ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($url) href="{{ $url }}" @endif {{ $attributes->class(['azana-kpi', 'azana-kpi-primary' => $primary, 'transition hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500' => $url]) }}>
    <span class="azana-kpi-label">{{ $label }}</span>
    <span class="azana-kpi-value {{ $toneClass }}">{{ $value }}</span>
    @if ($hint)
        <span class="azana-kpi-hint">{{ $hint }}</span>
    @endif
</{{ $tag }}>
