@props(['label'])

<label class="azana-filter">
    <span class="azana-filter-label">{{ $label }}</span>
    <x-filament::input.wrapper>
        <x-filament::input.select :attributes="$attributes">{{ $slot }}</x-filament::input.select>
    </x-filament::input.wrapper>
</label>
