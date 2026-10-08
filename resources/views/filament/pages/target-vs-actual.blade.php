<x-filament-panels::page>
    <x-erp.filters heading="Period" description="The month and year to compare against target.">
        <x-erp.filter-select label="Month" wire:model.live="month">
                @foreach ($this->monthOptions() as $number => $name)<option value="{{ $number }}">{{ $name }}</option>@endforeach
            
        </x-erp.filter-select>
        <x-erp.filter-select label="Year" wire:model.live="year">
                @foreach ($this->yearOptions() as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
            
        </x-erp.filter-select>
    </x-erp.filters>

    <h2 class="text-base font-semibold">{{ $this->periodLabel() }}</h2>
    @foreach (collect($this->kpis)->groupBy('area') as $area => $kpis)
        <section class="space-y-2">
            <h3 class="font-semibold">{{ \App\Domain\Reporting\KpiRegistry::AREAS[$area][0] }}</h3>
            @include('filament.pages.partials.kpi-table', ['kpis' => $kpis])
        </section>
    @endforeach
</x-filament-panels::page>
