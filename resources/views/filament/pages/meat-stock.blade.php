<x-filament-panels::page class="azana-report">
    @php($rows = $this->rows)
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500">No meat in the cold rooms.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2">Product</th><th class="px-4 py-2">Batch</th><th class="px-4 py-2">Use by</th>
                        <th class="px-4 py-2 text-right">kg</th><th class="px-4 py-2 text-right">Cost</th><th class="px-4 py-2">Cold room</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['product'] }}</td>
                            <td class="px-4 py-2"><a class="text-primary-600 hover:underline" href="{{ $this->batchUrl($row['production_batch_id']) }}">{{ $row['batch'] }}</a></td>
                            <td @class(['px-4 py-2', 'font-medium text-danger-600' => $row['expired']])>{{ $row['use_by']->format('d M Y') }}{{ $row['expired'] ? ' (expired)' : '' }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ $row['kg'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $this->money($row['value_minor']) }}</td>
                            <td class="px-4 py-2">{{ $row['store'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
