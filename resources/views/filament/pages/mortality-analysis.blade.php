<x-filament-panels::page>
    <x-erp.filters heading="Grouping and period" description="How to group the deaths and the dates to cover.">
        <x-erp.filter-select label="Group by" wire:model.live="dimension">
                    @foreach ($this->dimensions() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                
        </x-erp.filter-select>
        <x-erp.filter-date label="From" wire:model.live="from" />
        <x-erp.filter-date label="To" wire:model.live="to" />
    </x-erp.filters>

    @php($errors = $this->inputErrors())
    @if ($errors !== [])
        <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-danger-600">
            @foreach ($errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @else
        @php($analysis = $this->analysis)
        <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Total deaths in period: <strong>{{ $analysis['total'] }}</strong>
            (individual records plus pre-weaning losses of untracked piglets)</p>

        <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">{{ $this->dimensions()[$dimension] }}</th><th class="px-4 py-2">Deaths</th><th class="px-4 py-2">Share</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($analysis['rows'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['label'] }}</td><td class="px-4 py-2">{{ $row['count'] }}</td><td class="px-4 py-2">{{ $row['percent'] }}%</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-3 text-gray-500">No deaths in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
