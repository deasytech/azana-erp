<x-filament-panels::page>
    <x-erp.filters heading="Period" description="The month the indicators are read for.">
        <x-erp.filter-select label="Month" wire:model.live="month">
                    @foreach ($this->monthOptions() as $number => $name)<option value="{{ $number }}">{{ $name }}</option>@endforeach
                
        </x-erp.filter-select>
        <x-erp.filter-select label="Year" wire:model.live="year">
                    @foreach ($this->yearOptions() as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
                
        </x-erp.filter-select>
    </x-erp.filters>

    <div class="flex flex-wrap gap-2 text-sm">
        @foreach ($this->areas() as $key => $name)
            <button
                type="button"
                wire:click="$set('area', '{{ $key }}')"
                @class([
                    'inline-flex items-center rounded-lg border px-3 py-1.5 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-amber-400/40',
                    'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300' => $this->chosenArea() === $key,
                    'border-gray-300 dark:border-white/10 text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:border-white/10 dark:hover:bg-white/5' => $this->chosenArea() !== $key,
                ])
            >
                {{ $name }}
            </button>
        @endforeach
    </div>

    <x-filament::section
        :icon="Filament\Support\Icons\Heroicon::ChartBar"
        :heading="$this->areas()[$this->chosenArea()] . ' - ' . $this->periodLabel()"
    >
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->kpis as $k)
                @php
                    $targetHint = $k['target'] !== null
                        ? 'Target '.\App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['target'])
                            .($k['attainment_percent'] !== null ? ' ('.$k['attainment_percent'].'%)' : '')
                            .($k['status'] === 'met' ? ' - met' : ($k['status'] === 'missed' ? ' - missed' : ''))
                        : null;
                @endphp
                <x-erp.kpi :label="$k['label']" :value="\App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['value'])"
                    :hint="$targetHint" :tone="$k['status'] === 'met' ? 'success' : ($k['status'] === 'missed' ? 'danger' : null)" />
            @empty
                <p class="text-sm text-gray-500">Nothing to show for this area.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
