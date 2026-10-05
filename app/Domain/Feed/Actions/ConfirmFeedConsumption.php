<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Models\FeedProductionOrder;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\FeedProductionStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Production staff confirm how much of each material was actually used (zero if none), before the order is completed. */
class ConfirmFeedConsumption
{
    /**
     * @param  array<int, string>  $actuals  quantity actually used, in the item's unit, keyed by inventory item id
     */
    public function __invoke(FeedProductionOrder $order, array $actuals, ?User $actor = null): FeedProductionOrder
    {
        if ($actuals === []) {
            throw new DomainException('Enter at least one quantity.', 'actuals_required');
        }

        return DB::transaction(function () use ($order, $actuals, $actor) {
            $order = $this->lockPlanned($order);
            $lines = $order->lines->keyBy('inventory_item_id');

            foreach ($actuals as $itemId => $quantity) {
                $line = $lines->get((int) $itemId) ?? throw new DomainException('One of the materials is not part of this order.', 'invalid_item');
                $quantity = trim((string) $quantity);

                if (! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $quantity)) {
                    throw new DomainException('Each quantity must be zero or more, with at most 3 decimals.', 'actual_quantity');
                }

                $line->update(['actual_quantity' => $quantity, 'confirmed_by' => ($actor ?? Auth::user())?->getKey(), 'confirmed_at' => now()]);
            }

            return $order->load('lines');
        });
    }

    /** Confirms every material not yet confirmed as used exactly as planned. */
    public function asPlanned(FeedProductionOrder $order, ?User $actor = null): FeedProductionOrder
    {
        // Read the lines afresh: any already-loaded copy may predate figures just entered.
        $pending = $order->lines()->get()->filter(fn ($line) => ! $line->isConfirmed())->mapWithKeys(fn ($line) => [$line->inventory_item_id => (string) $line->planned_quantity])->all();

        return $pending === [] ? $order : ($this)($order, $pending, $actor);
    }

    private function lockPlanned(FeedProductionOrder $order): FeedProductionOrder
    {
        $order = FeedProductionOrder::lockForUpdate()->with('lines')->findOrFail($order->id);

        $order->status === FeedProductionStatus::Planned || throw new DomainException("{$order->number} is {$order->status->label()}: consumption can only be confirmed on a planned order.", 'order_not_planned');

        return $order;
    }
}
