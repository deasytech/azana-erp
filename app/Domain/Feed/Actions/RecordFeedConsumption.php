<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Inventory\Actions\IssueStock;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records feed eaten by a batch or by one animal. The cost (quantity x cost per kg) is snapshotted in whole
 * minor units. Naming a store (`inventory_location_id`) also draws the feed out of stock: the ledger then sets
 * the cost from what the stock actually cost, and the stock lines are linked to the record by `inventory_group`.
 */
class RecordFeedConsumption
{
    public function __construct(private readonly IssueStock $issueStock) {}

    /** @param array{notes?: ?string, cost_per_kg_minor?: ?int, idempotency_key?: ?string, inventory_location_id?: ?int} $details */
    public function __invoke(ProductionBatch|Animal $target, FeedType|int $feedType, CarbonInterface $consumedOn, string $quantityKg, array $details = [], ?User $actor = null): FeedConsumptionRecord
    {
        return DB::transaction(function () use ($target, $feedType, $consumedOn, $quantityKg, $details, $actor) {
            $isBatch = $target instanceof ProductionBatch;
            $key = $details['idempotency_key'] ?? null;

            if ($key && ($existing = FeedConsumptionRecord::firstWhere('idempotency_key', $key))) {
                $sameTarget = $isBatch ? $existing->production_batch_id === $target->id : $existing->animal_id === $target->id;

                return $sameTarget ? $existing : throw new DomainException('This idempotency key was already used for something else.', 'idempotency_conflict');
            }

            $feed = $feedType instanceof FeedType ? $feedType : FeedType::find($feedType);
            $this->validate($isBatch ? ProductionBatch::lockForUpdate()->findOrFail($target->id) : $target->refresh(), $feed, $consumedOn, $quantityKg, $details['cost_per_kg_minor'] ?? null);

            $perKg = $details['cost_per_kg_minor'] ?? null;
            $cost = $perKg === null ? null : Ratio::toWhole(bcmul($quantityKg, (string) $perKg, 4));
            $group = null;

            if (! empty($details['inventory_location_id'])) {
                [$group, $cost] = $this->drawFromStock($feed, (int) $details['inventory_location_id'], $consumedOn, $quantityKg, $target, $key, $actor);
                $perKg = Ratio::toWhole(bcdiv((string) $cost, $quantityKg, 6));
            }

            return FeedConsumptionRecord::create([
                'production_batch_id' => $isBatch ? $target->id : null,
                'animal_id' => $isBatch ? null : $target->id,
                'feed_type_id' => $feed->id,
                'consumed_on' => $consumedOn,
                'quantity_kg' => $quantityKg,
                'cost_per_kg_minor' => $perKg,
                'cost_minor' => $cost,
                'inventory_group' => $group,
                'notes' => $details['notes'] ?? null,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $key,
            ]);
        });
    }

    /** @return array{0: string, 1: int} the ledger group id and the cost of the stock used, in minor units */
    private function drawFromStock(FeedType $feed, int $locationId, CarbonInterface $on, string $quantityKg, ProductionBatch|Animal $target, ?string $key, ?User $actor): array
    {
        $item = InventoryItem::with('unit')->firstWhere('feed_type_id', $feed->id)
            ?? throw new DomainException("{$feed->name} is not linked to a stock item, so it cannot be taken from a store.", 'feed_not_stocked');

        $quantity = UnitOfMeasure::firstWhere('code', 'KG')->convertTo($quantityKg, $item->unit);
        $group = (string) Str::uuid();

        $lines = ($this->issueStock)(InventoryTransactionType::Consumption, $item, $locationId, $quantity, $on, [
            'group_uuid' => $group,
            'idempotency_key' => $key ? "feed:{$key}" : null,
            'source_type' => $target instanceof ProductionBatch ? 'production_batch' : 'animal',
            'source_id' => $target->id,
            'reason' => 'Feed consumption',
        ], $actor);

        return [$group, -$lines->sum('value_minor')];
    }

    private function validate(ProductionBatch|Animal $target, ?FeedType $feed, CarbonInterface $on, string $quantity, ?int $perKg): void
    {
        if (! $feed?->is_active) {
            throw new DomainException('Choose an active feed type.', 'invalid_feed_type');
        }

        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $quantity) || bccomp($quantity, '0', 2) <= 0) {
            throw new DomainException('The quantity must be a positive number of kilograms with at most 2 decimals.', 'feed_quantity');
        }

        if ($perKg !== null && $perKg < 0) {
            throw new DomainException('The cost per kg cannot be negative.', 'feed_cost');
        }

        if ($on->gt(now()->addMinutes(5))) {
            throw new DomainException('Feed cannot be dated in the future.', 'feed_future');
        }

        if ($target instanceof ProductionBatch) {
            ($target->isActive() && $on->gte($target->started_on)) || throw new DomainException("Batch {$target->code} is closed or had not started on that date.", 'feed_batch');

            return;
        }

        if (! $target->isActive() || ($target->birth_date && $on->lt($target->birth_date))) {
            throw new DomainException("{$target->animal_number} is not active, or was not born on that date.", 'feed_animal');
        }
    }
}
