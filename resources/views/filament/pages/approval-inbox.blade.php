<x-filament-panels::page class="azana-report">
    @php($items = $this->items)
    @if ($items->isEmpty())
        <p class="text-sm text-gray-500">Nothing is waiting for your approval.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5">
                    <tr><th class="px-4 py-2">What</th><th class="px-4 py-2">Reference</th><th class="px-4 py-2">Details</th><th class="px-4 py-2">Raised by</th><th class="px-4 py-2">Raised</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($items as $item)
                        <tr>
                            <td class="px-4 py-2">{{ $item['type'] }}</td>
                            <td class="px-4 py-2">{{ $item['reference'] }}</td>
                            <td class="px-4 py-2">{{ $item['detail'] }}</td>
                            <td class="px-4 py-2">{{ $item['mine'] ? 'You' : ($item['raised_by'] ?? '-') }}</td>
                            <td class="px-4 py-2">{{ $item['raised_at']?->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-right"><a href="{{ $item['url'] }}" class="text-primary-600 hover:underline">Review</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
