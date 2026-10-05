<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Feed\Concerns\ConvertsFeedQuantities;
use App\Domain\Feed\Events\FeedProductionCompleted;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Feed\Models\FeedProductionOrder;
use App\Domain\Inventory\Actions\IssueStock;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\FeedProductionStatus;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finishes a feed production order, all or nothing: the confirmed materials are taken out of the source store
 * (valued by the ledger), the finished feed is received into the output store as a new batch, and the cost is
 * worked out - materials plus any other costs, per kg of feed actually made.
 */
class CompleteFeedProduction
{
    use ConvertsFeedQuantities;

    public function __construct(
        private readonly IssueStock $issueStock,
        private readonly ReceiveStock $receiveStock,
        private readonly ResolveSettings $settings,
    ) {}

    public function __invoke(FeedProductionOrder $order, string $actualOutputKg, CarbonInterface $producedOn, int $otherCostMinor = 0, ?string $batchNumber = null, ?User $actor = null): FeedProductionBatch
    {
        $this->validateInput($actualOutputKg, $producedOn, $otherCostMinor);

        return DB::transaction(function () use ($order, $actualOutputKg, $producedOn, $otherCostMinor, $batchNumber, $actor) {
            $order = FeedProductionOrder::lockForUpdate()->with(['lines.item.unit', 'formula'])->findOrFail($order->id);

            $this->validateOrder($order, $producedOn);

            $group = (string) Str::uuid();
            $source = ['group_uuid' => $group, 'source_type' => 'feed_production', 'source_id' => $order->id];
            $materialCost = 0;

            foreach ($order->lines->filter(fn ($line) => bccomp((string) $line->actual_quantity, '0', 3) > 0) as $line) {
                $used = ($this->issueStock)(InventoryTransactionType::Consumption, $line->item, $order->source_location_id, (string) $line->actual_quantity, $producedOn, $source + ['reason' => "Used in {$order->number}"], $actor);
                $materialCost += -$used->sum('value_minor');
            }

            $finished = InventoryItem::with('unit')->where('feed_type_id', $order->formula->feed_type_id)->where('is_active', true)->first()
                ?? throw new DomainException('The feed type has no stock item to hold the finished feed.', 'feed_not_stocked');
            $totalCost = $materialCost + $otherCostMinor;

            $received = ($this->receiveStock)(InventoryTransactionType::Production, $finished, $order->output_location_id, $this->kgToItemUnit($actualOutputKg, $finished), $producedOn, $source + [
                'value_minor' => $totalCost,
                'batch_number' => $batchNumber ?: $order->number,
                'expiry_date' => $finished->tracks_expiry ? $producedOn->copy()->startOfDay()->addDays((int) $this->settings->get('feed.finished_feed_shelf_life_days')) : null,
                'reason' => "Produced on {$order->number}",
            ], $actor);

            $batch = FeedProductionBatch::create([
                'feed_production_order_id' => $order->id,
                'inventory_item_id' => $finished->id,
                'inventory_batch_id' => $received->inventory_batch_id,
                'inventory_location_id' => $order->output_location_id,
                'output_kg' => $actualOutputKg,
                'produced_on' => $producedOn,
                'material_cost_minor' => $materialCost,
                'other_cost_minor' => $otherCostMinor,
                'total_cost_minor' => $totalCost,
                'cost_per_kg_minor' => Ratio::toWhole(bcdiv((string) $totalCost, $actualOutputKg, 8)),
                'group_uuid' => $group,
                'produced_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            $order->update(['status' => FeedProductionStatus::Completed]);
            FeedProductionCompleted::dispatch($batch);

            return $batch;
        });
    }

    private function validateInput(string $outputKg, CarbonInterface $on, int $otherCost): void
    {
        if (! preg_match('/^\d{1,9}(\.\d{1,3})?$/', $outputKg) || bccomp($outputKg, '0', 3) <= 0) {
            throw new DomainException('The output must be a positive number of kilograms with at most 3 decimals.', 'output_kg');
        }

        if ($otherCost < 0) {
            throw new DomainException('Other costs cannot be negative.', 'other_cost');
        }

        if ($on->gt(now()->addMinutes(5))) {
            throw new DomainException('Production cannot be dated in the future.', 'production_future');
        }
    }

    private function validateOrder(FeedProductionOrder $order, CarbonInterface $on): void
    {
        if ($order->status !== FeedProductionStatus::Planned) {
            throw new DomainException("{$order->number} is {$order->status->label()}; only a planned order can be completed.", 'order_not_planned');
        }

        if ($order->lines->contains(fn ($line) => ! $line->isConfirmed())) {
            throw new DomainException('Confirm the quantity used of every material before completing.', 'consumption_unconfirmed');
        }

        if ($on->lt($order->planned_on->copy()->startOfDay()->subDays(30))) {
            throw new DomainException('Production is dated too long before the order was planned.', 'production_date');
        }
    }
}
