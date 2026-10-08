<x-filament-panels::page class="azana-report">
    @php($rows = $this->summary)
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500">No active batches.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2">Batch</th><th class="px-4 py-2">Stage</th><th class="px-4 py-2">Pigs</th><th class="px-4 py-2">Mortality</th>
                        <th class="px-4 py-2">Avg weight</th><th class="px-4 py-2">ADG (kg/day)</th><th class="px-4 py-2">FCR</th><th class="px-4 py-2">Expected market</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($rows as $row)
                        @php($p = $row['performance'])
                        <tr>
                            <td class="px-4 py-2"><a class="text-primary-600 hover:underline" href="{{ $this->batchUrl($row['batch']) }}">{{ $row['batch']->code }}</a> <span class="text-gray-500">{{ $row['batch']->name }}</span></td>
                            <td class="px-4 py-2">{{ $row['batch']->stage->name }}</td>
                            <td class="px-4 py-2">{{ $p['heads'] }}</td>
                            <td class="px-4 py-2">{{ $p['mortality_percent'] ?? '-' }}%</td>
                            <td class="px-4 py-2">{{ $p['latest_weight_kg'] ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $p['adg_kg'] ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $p['fcr'] ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $p['expected_market_on'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
