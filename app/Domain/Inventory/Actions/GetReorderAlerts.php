<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLayer;
use Illuminate\Support\Collection;

/** Active items whose stock across all stores has fallen to or below their reorder level. */
class GetReorderAlerts
{
    /** @return Collection<int, array{item: InventoryItem, on_hand: string, reorder_level: string, suggested_quantity: string}> */
    public function __invoke(): Collection
    {
        $held = InventoryLayer::where('remaining_quantity', '>', 0)->selectRaw('inventory_item_id, SUM(remaining_quantity) as on_hand')
            ->groupBy('inventory_item_id')->pluck('on_hand', 'inventory_item_id');

        return InventoryItem::with('unit')->where('is_active', true)->whereNotNull('reorder_level')->orderBy('name')->get()
            ->map(fn (InventoryItem $item) => [
                'item' => $item,
                'on_hand' => $onHand = bcadd((string) ($held[$item->id] ?? 0), '0', 3),
                'reorder_level' => (string) $item->reorder_level,
                // Order the usual quantity, or enough to climb back above the level.
                'suggested_quantity' => $item->reorder_quantity !== null ? (string) $item->reorder_quantity : bcsub((string) $item->reorder_level, $onHand, 3),
            ])
            ->filter(fn (array $row) => bccomp($row['on_hand'], $row['reorder_level'], 3) <= 0)
            ->values();
    }
}
