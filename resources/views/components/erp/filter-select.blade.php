@props(['label'])

@php($id = 'filter-'.\Illuminate\Support\Str::slug((string) $attributes->whereStartsWith('wire:model')->first()))

<div class="azana-filter">
    <label for="{{ $id }}" class="azana-filter-label">{{ $label }}</label>
    <x-filament::input.wrapper>
        <x-filament::input.select :attributes="$attributes->merge(['id' => $id])">{{ $slot }}</x-filament::input.select>
    </x-filament::input.wrapper>
</div>
