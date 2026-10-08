<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">Month
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="month">
                    @foreach ($this->monthOptions() as $number => $name)<option value="{{ $number }}">{{ $name }}</option>@endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <label class="flex flex-col gap-1">Year
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="year">
                    @foreach ($this->yearOptions() as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
    </div>

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
                <x-filament::card>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $k['label'] }}</div>
                    <div class="text-xl font-semibold text-gray-950 dark:text-white">
                        {{ \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['value']) }}
                    </div>
                    @if ($k['target'] !== null)
                        <div class="mt-2 text-xs leading-5 {{ $k['status'] === 'met'
                            ? 'text-success-600'
                            : ($k['status'] === 'missed' ? 'text-danger-600' : 'text-gray-500 dark:text-gray-400')
                            }}">
                            Target {{ \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['target']) }}@if ($k['attainment_percent'] !== null) ({{ $k['attainment_percent'] }}%)@endif
                            @if ($k['status'] === 'met')
                                - met
                            @elseif ($k['status'] === 'missed')
                                - missed
                            @endif
                        </div>
                    @endif
                </x-filament::card>
            @empty
                <p class="text-sm text-gray-500">Nothing to show for this area.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
