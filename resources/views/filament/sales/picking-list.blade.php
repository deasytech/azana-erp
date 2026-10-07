@php($rows = $getState())
<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
        <thead class="bg-gray-50 text-left dark:bg-white/5">
            <tr><th class="px-4 py-2">Item</th><th class="px-4 py-2">Take from</th><th class="px-4 py-2">Batch</th><th class="px-4 py-2">Use by</th><th class="px-4 py-2 text-right">Quantity</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($rows as $row)
                <tr>
                    <td class="px-4 py-2">{{ $row['what'] }}</td><td class="px-4 py-2">{{ $row['from'] }}</td>
                    <td class="px-4 py-2">{{ $row['batch'] ?? '-' }}</td><td class="px-4 py-2">{{ $row['use_by'] ?? '-' }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ $row['quantity'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
