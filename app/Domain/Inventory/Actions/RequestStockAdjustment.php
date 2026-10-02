<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Asks for a correction to one item's stock. Nothing moves until someone with approval rights approves it. */
class RequestStockAdjustment
{
    public function __construct(private readonly NextNumber $nextNumber) {}

    public function __invoke(InventoryItem|int $item, InventoryLocation|int $location, InventoryBatch|int|null $batch, string $quantity, string $reason, ?User $actor = null): StockAdjustment
    {
        if (! preg_match('/^-?\d{1,11}(\.\d{1,3})?$/', $quantity) || bccomp($quantity, '0', 3) === 0) {
            throw new DomainException('The adjustment must be a non-zero quantity: positive adds stock, negative removes it.', 'stock_quantity');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required for a stock adjustment.', 'reason_required');
        }

        $item = $item instanceof InventoryItem ? $item : InventoryItem::findOrFail($item);
        $batchId = $batch instanceof InventoryBatch ? $batch->id : $batch;

        if ($item->tracks_batches !== ($batchId !== null) || ($batchId && InventoryBatch::whereKey($batchId)->value('inventory_item_id') !== $item->id)) {
            throw new DomainException($item->tracks_batches ? "{$item->name} is tracked by batch: choose its batch." : "{$item->name} is not tracked by batch.", 'invalid_batch');
        }

        return DB::transaction(fn () => StockAdjustment::create([
            'number' => sprintf('ADJ-%06d', ($this->nextNumber)('stock_adjustment')),
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $location instanceof InventoryLocation ? $location->id : $location,
            'inventory_batch_id' => $batchId,
            'quantity' => $quantity,
            'reason' => trim($reason),
            'requested_by' => ($actor ?? Auth::user())?->getKey(),
        ]));
    }
}
