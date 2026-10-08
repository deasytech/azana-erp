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
        @php($t = $report['totals'])
        <p class="text-sm">
            {{ $t['carcasses'] }} carcasses ({{ $t['heads'] }} pigs): {{ $t['live_kg'] }} kg live, {{ $t['hot_kg'] }} kg carcass, {{ $t['condemned_kg'] }} kg condemned.
            Dressing {{ $t['dressing_percent'] !== null ? $t['dressing_percent'].'%' : '-' }} against a target of {{ $t['target_percent'] }}%.
        </p>

        @if ($report['by_day']->isNotEmpty())
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 text-left dark:bg-white/5">
                        <tr><th class="px-4 py-2">Day</th><th class="px-4 py-2 text-right">Pigs</th><th class="px-4 py-2 text-right">Live kg</th><th class="px-4 py-2 text-right">Carcass kg</th><th class="px-4 py-2 text-right">Dressing</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($report['by_day'] as $row)
                            <tr>
                                <td class="px-4 py-2">{{ $row['day'] }}</td><td class="px-4 py-2 text-right">{{ $row['heads'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['live_kg'] }}</td><td class="px-4 py-2 text-right">{{ $row['hot_kg'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['dressing_percent'] !== null ? $row['dressing_percent'].'%' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No pigs were slaughtered in this period.</p>
        @endif

        @if ($report['low']->isNotEmpty())
            <section class="space-y-2">
                <h2 class="text-base font-semibold">Dressed out low</h2>
                @foreach ($report['low'] as $carcass)
                    <div class="rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm dark:border-warning-700 dark:bg-warning-950">
                        {{ $carcass->number }}: {{ $carcass->dressing_percent }}% ({{ $carcass->hot_weight_kg }} kg from {{ $carcass->live_weight_kg }} kg live)
                    </div>
                @endforeach
            </section>
        @endif
    @endif
</x-filament-panels::page>
