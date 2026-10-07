<?php

namespace App\Domain\Sales\Services;

use App\Domain\Inventory\Services\StockValuation;
use App\Domain\Sales\Models\StockReservation;
use App\Enums\ReservationStatus;

/** Stock of a batch in a store that is free to promise: what is on hand less what open orders have reserved. */
class StockAvailability
{
    public function __construct(private readonly StockValuation $stock) {}

    public function free(int $itemId, int $locationId, int $inventoryBatchId): string
    {
        $held = (string) StockReservation::where('inventory_batch_id', $inventoryBatchId)->where('inventory_location_id', $locationId)
            ->where('status', ReservationStatus::Active)->sum('quantity');

        return bcsub($this->stock->onHand($itemId, $locationId, $inventoryBatchId), $held, 3);
    }
}
