<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">From
            <input type="date" wire:model.live="from" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
        </label>
        <label class="flex flex-col gap-1">To
            <input type="date" wire:model.live="to" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
        </label>
    </div>

    @foreach ($this->inputErrors() as $error)
        <p class="text-sm text-danger-600">{{ $error }}</p>
    @endforeach

    @if ($report = $this->report)
        @php($t = $report['totals'])
        <p class="text-sm">
            Revenue {{ \App\Filament\Support\MoneyColumn::format($t['revenue_minor']) }}, direct costs {{ \App\Filament\Support\MoneyColumn::format($t['direct_cost_minor']) }}: gross margin {{ \App\Filament\Support\MoneyColumn::format($t['gross_margin_minor']) }}
            ({{ $t['gross_margin_percent'] !== null ? $t['gross_margin_percent'].'%' : '-' }}).
            Overheads {{ \App\Filament\Support\MoneyColumn::format($t['overhead_minor']) }}: net margin {{ \App\Filament\Support\MoneyColumn::format($t['net_margin_minor']) }}
            ({{ $t['net_margin_percent'] !== null ? $t['net_margin_percent'].'%' : '-' }}).
        </p>
        @if ($t['unassigned_revenue_minor'] || $t['unassigned_overhead_minor'])
            <p class="text-sm text-gray-500">Not assigned to a cost centre: revenue {{ \App\Filament\Support\MoneyColumn::format($t['unassigned_revenue_minor']) }}, overheads {{ \App\Filament\Support\MoneyColumn::format($t['unassigned_overhead_minor']) }}.</p>
        @endif
        @if ($report['rows'])
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">Business unit</th><th class="px-4 py-2 text-right">Revenue</th><th class="px-4 py-2 text-right">Direct cost</th><th class="px-4 py-2 text-right">Gross margin</th><th class="px-4 py-2 text-right">Overheads</th><th class="px-4 py-2 text-right">Net margin</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report['rows'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['centre']->name }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['revenue_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['direct_cost_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['gross_margin_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['overhead_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['net_margin_minor']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="text-sm text-gray-500">No revenue or costs in this period.</p>
        @endif
    @endif
</x-filament-panels::page>
