<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Enums\PurchaseOrderStatus;

/** Sets an approved order to partially received / received / back to approved from what has actually been received. */
class SyncPurchaseOrderReceiptStatus
{
    public function __invoke(PurchaseOrder $order): PurchaseOrder
    {
        $lines = $order->lines()->get();
        $received = $lines->map(fn ($line) => $line->receivedQuantity());

        $status = match (true) {
            $lines->isNotEmpty() && $lines->every(fn ($line, $i) => bccomp($received[$i], (string) $line->quantity, 3) >= 0) => PurchaseOrderStatus::Received,
            $received->contains(fn ($quantity) => bccomp($quantity, '0', 3) > 0) => PurchaseOrderStatus::PartiallyReceived,
            default => PurchaseOrderStatus::Approved,
        };

        $order->update(['status' => $status]);

        return $order;
    }
}
