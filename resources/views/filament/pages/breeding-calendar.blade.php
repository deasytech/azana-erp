<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">From <input type="date" wire:model.live="from" class="fi-input block rounded-lg border-gray-300 dark:bg-gray-900"></label>
        <label class="text-sm">To <input type="date" wire:model.live="to" class="fi-input block rounded-lg border-gray-300 dark:bg-gray-900"></label>
    </div>

    @php($entries = $this->entries)
    <div class="mt-6 space-y-6">
        @forelse ($entries->groupBy(fn ($e) => $e['date']->toDateString()) as $date => $day)
            <section>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ \Carbon\Carbon::parse($date)->format('D d M Y') }}</h3>
                <ul class="mt-2 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($day as $entry)
                        <li class="flex flex-wrap gap-x-4 px-4 py-2 text-sm">
                            <span class="w-52 font-medium">{{ $entry['type'] }}</span>
                            <a class="text-primary-600 hover:underline" href="{{ $this->animalUrl($entry['sow_id']) }}">{{ $entry['sow'] }}</a>
                            <span class="text-gray-500 dark:text-gray-400">{{ $entry['detail'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="text-sm text-gray-500">Nothing is expected in this period.</p>
        @endforelse
    </div>
</x-filament-panels::page>
