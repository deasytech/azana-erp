<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryLayer;
use Illuminate\Support\Collection;

/** Stock on hand and its value, read from the cost layers (which always agree with the ledger). */
class GetStockLevels
{
    /**
     * One row per item, store and batch holding stock.
     *
     * @return Collection<int, InventoryLayer> with extra attributes on_hand (decimal string) and value_minor
     */
    public function __invoke(?int $itemId = null, ?int $locationId = null): Collection
    {
        return InventoryLayer::with(['item.unit', 'location', 'batch'])
            ->selectRaw('inventory_item_id, inventory_location_id, inventory_batch_id, SUM(remaining_quantity) as on_hand, SUM(remaining_value_minor) as value_minor')
            ->where('remaining_quantity', '>', 0)
            ->when($itemId, fn ($q) => $q->where('inventory_item_id', $itemId))
            ->when($locationId, fn ($q) => $q->where('inventory_location_id', $locationId))
            ->groupBy('inventory_item_id', 'inventory_location_id', 'inventory_batch_id')
            ->get()
            ->each(function (InventoryLayer $row) {
                // Sums come back as strings or numbers depending on the database; keep them exact strings and ints.
                $row->on_hand = bcadd((string) $row->on_hand, '0', 3);
                $row->value_minor = (int) $row->value_minor;
            })
            ->sortBy(fn (InventoryLayer $row) => [$row->item->name, $row->location->name, $row->batch?->batch_number])
            ->values();
    }

    /** Quantity of an item across every store and batch. */
    public function total(int $itemId): string
    {
        return bcadd((string) InventoryLayer::where('inventory_item_id', $itemId)->where('remaining_quantity', '>', 0)->sum('remaining_quantity'), '0', 3);
    }

    /** Total value of all stock, in minor units. */
    public function totalValue(): int
    {
        return (int) InventoryLayer::where('remaining_quantity', '>', 0)->sum('remaining_value_minor');
    }
}
