<x-filament-panels::page>
    @php($rows = $this->rows)
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500">No semen in stock.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2">Breed</th><th class="px-4 py-2">Boar</th><th class="px-4 py-2">Batch</th><th class="px-4 py-2">Expires</th>
                        <th class="px-4 py-2 text-right">Doses</th><th class="px-4 py-2">Store</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Sellable</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['breed'] ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $row['boar'] }}</td>
                            <td class="px-4 py-2"><a class="text-primary-600 hover:underline" href="{{ $this->batchUrl($row['semen_batch']) }}">{{ $row['batch'] }}</a></td>
                            <td @class(['px-4 py-2', 'text-danger-600' => $row['expiry_date']->lt(now()->startOfDay())])>{{ $row['expiry_date']->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ $row['doses'] }}</td>
                            <td class="px-4 py-2">{{ $row['store'] }}</td>
                            <td class="px-4 py-2">{{ $row['status'] }}</td>
                            <td class="px-4 py-2">{{ $row['sellable'] ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
