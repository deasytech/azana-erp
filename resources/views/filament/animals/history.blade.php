@php($entries = $getState())
<ol class="space-y-3">
    @forelse ($entries as $entry)
        <li class="flex gap-3 text-sm">
            <span class="w-32 shrink-0 text-gray-500 dark:text-gray-400">{{ $entry['at']->format('d M Y H:i') }}</span>
            <span class="w-24 shrink-0 font-medium">{{ $entry['type'] }}</span>
            <span>
                {{ $entry['title'] }}
                @if ($entry['detail'])
                    <span class="text-gray-500 dark:text-gray-400">- {{ $entry['detail'] }}</span>
                @endif
                @if ($entry['user'])
                    <span class="text-gray-400">({{ $entry['user'] }})</span>
                @endif
            </span>
        </li>
    @empty
        <li class="text-sm text-gray-500">No history yet.</li>
    @endforelse
</ol>
