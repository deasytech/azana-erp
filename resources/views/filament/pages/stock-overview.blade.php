<x-filament-panels::page class="azana-report">
    <x-erp.filters heading="Stock filters" description="Narrow the stock list by item or store.">
        <x-erp.filter-select label="Item" wire:model.live="itemId">
                    <option value="">All items</option>
                    @foreach ($this->itemOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                
        </x-erp.filter-select>
        <x-erp.filter-select label="Store" wire:model.live="locationId">
                    <option value="">All stores</option>
                    @foreach ($this->storeOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                
        </x-erp.filter-select>
        <x-slot:aside><span class="text-gray-500">Total value</span> <strong class="text-base tabular-nums">{{ $this->totalValue }}</strong></x-slot:aside>
    </x-erp.filters>

    @php($levels = $this->levels)
    @if ($levels->isEmpty())
        <p class="text-sm text-gray-500">No stock on hand.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2">Item</th><th class="px-4 py-2">Store</th><th class="px-4 py-2">Batch</th>
                        <th class="px-4 py-2">Expires</th><th class="px-4 py-2 text-right">On hand</th><th class="px-4 py-2 text-right">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($levels as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row->item->name }} <span class="text-gray-500">{{ $row->item->code }}</span></td>
                            <td class="px-4 py-2">{{ $row->location->name }}</td>
                            <td class="px-4 py-2">{{ $row->batch?->batch_number ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $row->batch?->expiry_date?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-2 text-right">{{ $row->on_hand }} {{ $row->item->unit->code }}</td>
                            <td class="px-4 py-2 text-right">{{ $this->money($row->value_minor) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
