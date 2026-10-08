@props(['label'])

<label class="azana-filter">
    <span class="azana-filter-label">{{ $label }}</span>
    <x-filament::input.wrapper>
        <x-filament::input type="date" :attributes="$attributes" />
    </x-filament::input.wrapper>
</label>
