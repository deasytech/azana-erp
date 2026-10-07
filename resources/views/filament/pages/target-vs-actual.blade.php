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

    <h2 class="text-base font-semibold">{{ $this->periodLabel() }}</h2>
    @foreach (collect($this->kpis)->groupBy('area') as $area => $kpis)
        <section class="space-y-2">
            <h3 class="font-semibold">{{ \App\Domain\Reporting\KpiRegistry::AREAS[$area][0] }}</h3>
            @include('filament.pages.partials.kpi-table', ['kpis' => $kpis])
        </section>
    @endforeach
</x-filament-panels::page>
