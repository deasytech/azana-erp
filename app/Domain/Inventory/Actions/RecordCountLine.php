<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockCountLine;
use App\Domain\Inventory\Services\StockValuation;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\StockCountStatus;
use App\Support\Ratio;
use Illuminate\Support\Facades\DB;

/**
 * Enters (or corrects) the quantity physically counted for an item and batch. Stock the system does not know
 * about can be added the same way: its system quantity is 0.
 */
class RecordCountLine
{
    public function __construct(private readonly StockValuation $valuation) {}

    public function __invoke(StockCount $count, InventoryItem|int $item, InventoryBatch|int|null $batch, string $countedQuantity, ?string $reason = null): StockCountLine
    {
        return DB::transaction(function () use ($count, $item, $batch, $countedQuantity, $reason) {
            $count = StockCount::lockForUpdate()->findOrFail($count->id);

            if ($count->status !== StockCountStatus::Draft) {
                throw new DomainException('Only a count that has not been submitted can be changed.', 'count_not_draft');
            }

            if (! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $countedQuantity)) {
                throw new DomainException('The counted quantity must be zero or more, with at most 3 decimals.', 'count_quantity');
            }

            $item = $item instanceof InventoryItem ? $item : InventoryItem::findOrFail($item);
            $batchId = $batch instanceof InventoryBatch ? $batch->id : $batch;

            if ($item->tracks_batches !== ($batchId !== null) || ($batchId && InventoryBatch::whereKey($batchId)->value('inventory_item_id') !== $item->id)) {
                throw new DomainException($item->tracks_batches ? "{$item->name} is counted by batch." : "{$item->name} is not tracked by batch.", 'invalid_batch');
            }

            $line = $count->lines()->firstOrNew(['inventory_item_id' => $item->id, 'inventory_batch_id' => $batchId]);

            if (! $line->exists) {
                $line->system_quantity = $this->valuation->onHand($item->id, $count->inventory_location_id, $batchId);
            }

            $variance = bcsub($countedQuantity, (string) $line->system_quantity, 3);

            $line->fill([
                'counted_quantity' => $countedQuantity,
                'variance_quantity' => $variance,
                'variance_value_minor' => Ratio::toWhole(bcmul($variance, (string) $this->valuation->currentUnitCost($item->id), 6)),
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            ])->save();

            return $line;
        });
    }
}
