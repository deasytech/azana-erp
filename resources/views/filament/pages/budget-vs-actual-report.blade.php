<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">Budget
            <select wire:model.live="budgetId" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                @foreach ($this->budgets() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">Through month
            <select wire:model.live="month" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                <option value="">Current</option>
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if ($report = $this->report)
        @php($t = $report['totals'])
        <p class="text-sm">
            January to {{ date('F', mktime(0, 0, 0, $report['through_month'], 1)) }}: revenue {{ \App\Filament\Support\MoneyColumn::format($t['revenue_actual_minor']) }} against a plan of {{ \App\Filament\Support\MoneyColumn::format($t['revenue_budget_minor']) }}; expenses {{ \App\Filament\Support\MoneyColumn::format($t['expense_actual_minor']) }} against {{ \App\Filament\Support\MoneyColumn::format($t['expense_budget_minor']) }}.
        </p>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">Account</th><th class="px-4 py-2">Cost centre</th><th class="px-4 py-2 text-right">Budget</th><th class="px-4 py-2 text-right">Actual</th><th class="px-4 py-2 text-right">Variance</th><th class="px-4 py-2 text-right">%</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report['rows'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['account']->label() }}</td><td class="px-4 py-2">{{ $row['cost_centre']->name ?? '-' }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['budget_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['actual_minor']) }}</td>
                            <td class="px-4 py-2 text-right {{ $row['favourable'] ? 'text-success-600' : 'text-danger-600' }}">{{ \App\Filament\Support\MoneyColumn::format($row['variance_minor']) }}</td><td class="px-4 py-2 text-right">{{ $row['variance_percent'] !== null ? $row['variance_percent'].'%' : '-' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">There is no budget yet. Make one under Finance > Budgets.</p>
    @endif
</x-filament-panels::page>
