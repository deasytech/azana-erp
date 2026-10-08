@props(['heading' => 'Filters', 'description' => null])

{{-- The bar of controls at the top of a report or calendar page: a card with its fields in a row; anything in the "aside" slot sits at the end. --}}
<div {{ $attributes->class('azana-filters') }}>
    <div class="azana-filters-head">
        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedAdjustmentsHorizontal" class="h-5 w-5 text-primary-500" />
        <div>
            <p class="azana-filters-title">{{ $heading }}</p>
            @if ($description)
                <p class="azana-filters-description">{{ $description }}</p>
            @endif
        </div>
    </div>
    <div class="azana-filters-body">
        {{ $slot }}
        @isset($aside)
            <div class="azana-filters-aside">{{ $aside }}</div>
        @endisset
    </div>
</div>
