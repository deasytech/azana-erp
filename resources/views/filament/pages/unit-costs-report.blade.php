<x-filament-panels::page class="azana-report">
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
        <h2 class="text-base font-semibold">Pigs</h2>
        @if ($report['pigs'])
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">Batch</th><th class="px-4 py-2 text-right">Pigs</th><th class="px-4 py-2 text-right">Total cost</th><th class="px-4 py-2 text-right">Cost per pig</th><th class="px-4 py-2 text-right">Live weight (kg)</th><th class="px-4 py-2 text-right">Cost per kg live weight</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report['pigs'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['batch'] }} - {{ $row['name'] }}</td><td class="px-4 py-2 text-right">{{ $row['pigs'] }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['total_cost_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['cost_per_pig_minor']) }}</td><td class="px-4 py-2 text-right">{{ $row['live_weight_kg'] ?? '-' }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['cost_per_kg_live_minor']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="text-sm text-gray-500">No batch has costs yet.</p>
        @endif

        <h2 class="text-base font-semibold">Feed made in the period</h2>
        @if ($report['feed'])
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">Feed</th><th class="px-4 py-2 text-right">Date</th><th class="px-4 py-2 text-right">Output (kg)</th><th class="px-4 py-2 text-right">Materials</th><th class="px-4 py-2 text-right">Other</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-right">Per kg</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report['feed'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['feed'] }}</td><td class="px-4 py-2 text-right">{{ $row['produced_on']->format('d M Y') }}</td><td class="px-4 py-2 text-right">{{ $row['output_kg'] }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['material_cost_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['other_cost_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['total_cost_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['cost_per_kg_minor']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="text-sm text-gray-500">No feed was made in this period.</p>
        @endif

        <h2 class="text-base font-semibold">Semen released in the period</h2>
        @if ($report['semen'])
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">Batch</th><th class="px-4 py-2 text-right">Boar</th><th class="px-4 py-2 text-right">Doses</th><th class="px-4 py-2 text-right">Cost per dose</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report['semen'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['batch'] }}</td><td class="px-4 py-2 text-right">{{ $row['boar'] }}</td><td class="px-4 py-2 text-right">{{ $row['doses'] }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['cost_per_dose_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['total_cost_minor']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="text-sm text-gray-500">No semen was released in this period.</p>
        @endif

        <h2 class="text-base font-semibold">Meat made in the period</h2>
        @if ($report['meat'])
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">Product</th><th class="px-4 py-2 text-right">Weight (kg)</th><th class="px-4 py-2 text-right">Total cost</th><th class="px-4 py-2 text-right">Cost per kg</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report['meat'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['product'] }}</td><td class="px-4 py-2 text-right">{{ $row['kg'] }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['total_cost_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['cost_per_kg_minor']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="text-sm text-gray-500">No meat was made in this period.</p>
        @endif
    @endif
</x-filament-panels::page>
