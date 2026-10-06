<?php

namespace App\Domain\Semen\Concerns;

use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Actions\IssueStock;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\InventoryTransactionType;
use App\Models\User;

/** Takes whatever doses of a batch are still in stock out of the ledger as wastage, and blocks the batch from stock. */
trait WritesOffSemenStock
{
    private function writeOff(SemenBatch $batch, string $reason, ?User $actor = null): void
    {
        if (! $batch->inventory_batch_id) {
            return;
        }

        $inventoryBatch = $batch->inventoryBatch()->first();
        $inventoryBatch->update(['is_active' => true]);   // a quarantined batch is blocked, and a blocked batch cannot be issued from

        foreach (app(GetStockLevels::class)($inventoryBatch->inventory_item_id)->where('inventory_batch_id', $batch->inventory_batch_id) as $row) {
            app(IssueStock::class)(InventoryTransactionType::Wastage, $row->inventory_item_id, $row->inventory_location_id, $row->on_hand, now()->startOfDay(), [
                'batch' => $batch->inventory_batch_id, 'reason' => $reason, 'source_type' => 'semen_batch', 'source_id' => $batch->id,
            ], $actor);
        }

        $inventoryBatch->update(['is_active' => false]);
    }
}
