<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">Month
            <select wire:model.live="month" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                @foreach ($this->monthOptions() as $number => $name)<option value="{{ $number }}">{{ $name }}</option>@endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">Year
            <select wire:model.live="year" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                @foreach ($this->yearOptions() as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
            </select>
        </label>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
        @foreach ($this->areas() as $key => $name)
            <button type="button" wire:click="$set('area', '{{ $key }}')" @class(['rounded-lg border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-950' => $this->chosenArea() === $key, 'border-gray-300 dark:border-white/10' => $this->chosenArea() !== $key])>{{ $name }}</button>
        @endforeach
    </div>

    <h2 class="text-base font-semibold">{{ $this->areas()[$this->chosenArea()] }} - {{ $this->periodLabel() }}</h2>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->kpis as $k)
            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs text-gray-500">{{ $k['label'] }}</div>
                <div class="text-xl font-semibold">{{ \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['value']) }}</div>
                @if ($k['target'] !== null)
                    <div @class(['text-xs', 'text-success-600' => $k['status'] === 'met', 'text-danger-600' => $k['status'] === 'missed', 'text-gray-500' => $k['status'] === 'none'])>
                        Target {{ \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['target']) }}@if ($k['attainment_percent'] !== null) ({{ $k['attainment_percent'] }}%)@endif
                        @if ($k['status'] === 'met') - met @elseif ($k['status'] === 'missed') - missed @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500">Nothing to show for this area.</p>
        @endforelse
    </div>
</x-filament-panels::page>
