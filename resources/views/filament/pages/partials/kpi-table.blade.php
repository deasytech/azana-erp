<x-filament::card>
    @php($labels = ['met' => 'Met', 'missed' => 'Missed', 'none' => ''])
    <div class="overflow-x-auto">
        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 text-left dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Indicator</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Actual</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Target</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Reached</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-500 dark:text-gray-400">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                @foreach ($kpis as $k)
                    <tr>
                        <td class="px-4 py-3">{{ $k['label'] }}</td>
                        <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['value']) }}</td>
                        <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ $k['target'] !== null ? \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['target']) : '-' }}</td>
                        <td class="px-4 py-3 text-right text-gray-950 dark:text-white">{{ $k['attainment_percent'] !== null ? $k['attainment_percent'].'%' : '-' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $k['status'] === 'met'
                                    ? 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-300'
                                    : ($k['status'] === 'missed'
                                        ? 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-300'
                                        : 'bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400'
                                    )
                                }}"
                            >
                                {{ $labels[$k['status']] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament::card>
