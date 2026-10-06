<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Models\StockReservation;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ReservationStatus;
use App\Enums\SalesOrderStatus;
use Illuminate\Support\Facades\DB;

/** Cancels an order that has not been dispatched and gives back whatever it had reserved. */
class CancelSalesOrder
{
    public function __invoke(SalesOrder $order, string $reason): SalesOrder
    {
        trim($reason) !== '' || throw new DomainException('A reason is required to cancel an order.', 'reason_required');

        return DB::transaction(function () use ($order, $reason) {
            $order = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [SalesOrderStatus::Draft, SalesOrderStatus::Confirmed], true)) {
                throw new DomainException("{$order->number} is {$order->status->label()} and can no longer be cancelled.", 'order_state');
            }

            StockReservation::whereIn('sales_order_line_id', $order->lines()->pluck('id'))->where('status', ReservationStatus::Active)->update(['status' => ReservationStatus::Released]);
            $order->update(['status' => SalesOrderStatus::Cancelled, 'cancel_reason' => trim($reason)]);

            return $order;
        });
    }
}
