<x-filament-panels::page>
    <x-erp.filters heading="Report month" description="The month this report is generated for.">
        <x-erp.filter-select label="Month" wire:model.live="month">
                @foreach ($this->monthOptions() as $number => $name)<option value="{{ $number }}">{{ $name }}</option>@endforeach
            
        </x-erp.filter-select>
        <x-erp.filter-select label="Year" wire:model.live="year">
                @foreach ($this->yearOptions() as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
            
        </x-erp.filter-select>
    </x-erp.filters>

    @php($r = $this->report)
    <p class="text-sm text-gray-500">{{ $r['label'] }}. Generated {{ $r['generated_at']->format('d M Y H:i') }} by {{ $r['generated_by'] }}.</p>

    @foreach (collect($r['kpis'])->groupBy('area') as $area => $kpis)
        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ \App\Domain\Reporting\KpiRegistry::AREAS[$area][0] }}</h2>
            @include('filament.pages.partials.kpi-table', ['kpis' => $kpis])
        </section>
    @endforeach

    @if ($r['profitability'])
        @php($t = $r['profitability']['totals'])
        <section class="space-y-2">
            <h2 class="text-base font-semibold">Profit</h2>
            <p class="text-sm">Revenue {{ \App\Filament\Support\MoneyColumn::format($t['revenue_minor']) }}, gross margin {{ \App\Filament\Support\MoneyColumn::format($t['gross_margin_minor']) }} ({{ $t['gross_margin_percent'] ?? '-' }}%), net margin {{ \App\Filament\Support\MoneyColumn::format($t['net_margin_minor']) }} ({{ $t['net_margin_percent'] ?? '-' }}%).</p>
        </section>
    @endif
    @if ($r['cash_flow'])
        <section class="space-y-2">
            <h2 class="text-base font-semibold">Cash</h2>
            <p class="text-sm">Opening {{ \App\Filament\Support\MoneyColumn::format($r['cash_flow']['opening_minor']) }}, in {{ \App\Filament\Support\MoneyColumn::format($r['cash_flow']['in_minor']) }}, out {{ \App\Filament\Support\MoneyColumn::format($r['cash_flow']['out_minor']) }}, closing {{ \App\Filament\Support\MoneyColumn::format($r['cash_flow']['closing_minor']) }}.</p>
        </section>
    @endif
    @if ($r['ageing'])
        <section class="space-y-2">
            <h2 class="text-base font-semibold">Owed and owing</h2>
            <p class="text-sm">Customers owe {{ \App\Filament\Support\MoneyColumn::format($r['ageing']['receivables']['total_minor']) }}; suppliers are owed {{ \App\Filament\Support\MoneyColumn::format($r['ageing']['payables']['total_minor']) }}.</p>
        </section>
    @endif
    @if ($r['budget'])
        <section class="space-y-2">
            <h2 class="text-base font-semibold">Budget: {{ $r['budget']['name'] }}</h2>
            <p class="text-sm">Revenue {{ \App\Filament\Support\MoneyColumn::format($r['budget']['totals']['revenue_actual_minor']) }} against {{ \App\Filament\Support\MoneyColumn::format($r['budget']['totals']['revenue_budget_minor']) }}; expenses {{ \App\Filament\Support\MoneyColumn::format($r['budget']['totals']['expense_actual_minor']) }} against {{ \App\Filament\Support\MoneyColumn::format($r['budget']['totals']['expense_budget_minor']) }}.</p>
        </section>
    @endif
    @if ($r['tasks'])
        <section class="space-y-2">
            <h2 class="text-base font-semibold">Tasks</h2>
            <p class="text-sm">{{ $r['tasks']['created'] }} created, {{ $r['tasks']['completed'] }} completed this month; {{ $r['tasks']['overdue'] }} overdue.</p>
        </section>
    @endif
    <section class="space-y-2">
        <h2 class="text-base font-semibold">Alerts standing now</h2>
        <p class="text-sm">{{ $r['alerts']['danger'] }} critical, {{ $r['alerts']['warning'] }} warnings, {{ $r['alerts']['info'] }} for information.</p>
    </section>
</x-filament-panels::page>
