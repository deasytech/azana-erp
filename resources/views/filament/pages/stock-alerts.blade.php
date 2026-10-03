<x-filament-panels::page>
    <section class="space-y-2">
        <h2 class="text-base font-semibold">Running low</h2>
        @forelse ($this->reorder as $row)
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm dark:border-warning-700 dark:bg-warning-950">
                <span class="font-medium">{{ $row['item']->name }}</span>
                <span>{{ $row['on_hand'] }} {{ $row['item']->unit->code }} in stock (reorder at {{ $row['reorder_level'] }})</span>
                <span class="ml-auto text-gray-600 dark:text-gray-300">Suggested order: {{ $row['suggested_quantity'] }} {{ $row['item']->unit->code }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-500">Nothing is below its reorder level.</p>
        @endforelse
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Expiry</h2>
        @forelse ($this->expiry as $row)
            <div @class([
                'flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3 text-sm',
                'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950' => $row['expired'],
                'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950' => ! $row['expired'],
            ])>
                <span class="font-medium">{{ $row['batch']->item->name }}</span>
                <span>Batch {{ $row['batch']->batch_number }}: {{ $row['on_hand'] }} {{ $row['batch']->item->unit->code }}</span>
                <span class="ml-auto">{{ $row['expired'] ? 'Expired '.$row['batch']->expiry_date->format('d M Y') : 'Expires '.$row['batch']->expiry_date->format('d M Y').' ('.$row['days_left'].' days)' }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-500">No stock is expired or close to expiry.</p>
        @endforelse
    </section>
</x-filament-panels::page>
