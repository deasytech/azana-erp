<x-filament-panels::page class="azana-report">
    <x-erp.filters heading="Period" description="Semen collected and processed between these dates.">
        <x-erp.filter-date label="From" wire:model.live="from" />
        <x-erp.filter-date label="To" wire:model.live="to" />
    </x-erp.filters>

    @foreach ($this->inputErrors() as $error)
        <p class="text-sm text-danger-600">{{ $error }}</p>
    @endforeach

    @if ($report = $this->report)
        <p class="text-sm">
            {{ $report['totals']['collections'] }} collections, {{ $report['totals']['doses'] }} doses made against a target of {{ $report['totals']['target_doses'] }};
            QC pass rate {{ $report['totals']['pass_rate_percent'] !== null ? $report['totals']['pass_rate_percent'].'%' : '-' }}.
        </p>

        @if ($report['rows']->isEmpty())
            <p class="text-sm text-gray-500">No collections in this period.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 text-left dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2">Boar</th><th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right">Collections</th><th class="px-4 py-2 text-right">Passed</th>
                            <th class="px-4 py-2 text-right">Failed</th><th class="px-4 py-2 text-right">Doses</th><th class="px-4 py-2 text-right">Target</th><th class="px-4 py-2 text-right">Against target</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($report['rows'] as $row)
                            <tr>
                                <td class="px-4 py-2">{{ $row['boar'] }}</td><td class="px-4 py-2">{{ $row['status'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['collections'] }}</td><td class="px-4 py-2 text-right">{{ $row['passed'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['failed'] }}</td><td class="px-4 py-2 text-right">{{ $row['doses'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['target_doses'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['attainment_percent'] !== null ? $row['attainment_percent'].'%' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
