<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Feed\Models\FeedProductionOrder;
use App\Domain\Feed\Models\FeedProductionOrderLine;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Actions\ReverseInventoryTransaction;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\FeedProductionStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Cancelling a plan, undoing a completed run, and checking whether the store holds what a plan needs. */
class ManageFeedProductionOrder
{
    public function __construct(private readonly ReverseInventoryTransaction $reverse, private readonly GetStockLevels $levels) {}

    public function cancel(FeedProductionOrder $order, string $reason): FeedProductionOrder
    {
        $this->requireReason($reason);

        return DB::transaction(function () use ($order, $reason) {
            $order = FeedProductionOrder::lockForUpdate()->findOrFail($order->id);
            $order->status === FeedProductionStatus::Planned || throw new DomainException('Only a planned order can be cancelled.', 'order_not_planned');
            $order->update(['status' => FeedProductionStatus::Cancelled, 'cancel_reason' => trim($reason)]);

            return $order;
        });
    }

    /**
     * Undoes a completed run: the finished feed leaves stock again and the materials return at the cost they left
     * with. Refused when any of the finished feed has already been used or sold.
     */
    public function reverse(FeedProductionOrder $order, string $reason, ?User $actor = null): FeedProductionBatch
    {
        $this->requireReason($reason);

        return DB::transaction(function () use ($order, $reason, $actor) {
            $order = FeedProductionOrder::lockForUpdate()->findOrFail($order->id);
            $batch = FeedProductionBatch::where('feed_production_order_id', $order->id)->lockForUpdate()->first();

            ($order->status === FeedProductionStatus::Completed && $batch && ! $batch->isReversed()) || throw new DomainException('Only a completed production run can be reversed.', 'order_not_completed');

            ($this->reverse)->group($batch->group_uuid, "Feed production {$order->number} reversed: ".trim($reason), $actor);

            $batch->forceFill(['reversed_at' => now(), 'reversed_by' => ($actor ?? Auth::user())?->getKey(), 'reverse_reason' => trim($reason)])->save();
            $order->update(['status' => FeedProductionStatus::Reversed]);

            return $batch;
        });
    }

    /**
     * What the source store holds against what the plan needs.
     *
     * @return Collection<int, array{line: FeedProductionOrderLine, on_hand: string, short: string}>
     */
    public function availability(FeedProductionOrder $order): Collection
    {
        $held = $this->levels->__invoke(null, $order->source_location_id)->groupBy('inventory_item_id')
            ->map(fn (Collection $rows) => $rows->reduce(fn (string $sum, $row) => bcadd($sum, $row->on_hand, 3), '0'));

        return $order->lines()->with('item.unit')->get()->map(function ($line) use ($held) {
            $onHand = (string) ($held[$line->inventory_item_id] ?? '0.000');
            $short = bcsub((string) $line->planned_quantity, $onHand, 3);

            return ['line' => $line, 'on_hand' => bcadd($onHand, '0', 3), 'short' => bccomp($short, '0', 3) > 0 ? $short : '0.000'];
        });
    }

    private function requireReason(string $reason): void
    {
        trim($reason) !== '' || throw new DomainException('A reason is required.', 'reason_required');
    }
}
