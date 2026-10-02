<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Events\InventoryTransactionPosted;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Inventory\Services\StockValuation;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock changes: appends one line to the ledger for one item, location and batch, and keeps
 * the cost layers in step. The quantity is signed (positive adds stock, negative removes it) and must agree
 * with the transaction type. Stock can never go negative.
 *
 * $details keys: batch (InventoryBatch|int), unit_cost_minor, value_minor (total, for additions), reason,
 * source_type, source_id, group_uuid, idempotency_key.
 * Additions need a cost, except returns and adjustments, which default to the item's current unit cost.
 */
class PostInventoryTransaction
{
    public function __construct(private readonly StockValuation $valuation) {}

    /** @param array<string, mixed> $details */
    public function __invoke(InventoryTransactionType $type, InventoryItem|int $item, InventoryLocation|int $location, string $quantity, CarbonInterface $occurredOn, array $details = [], ?User $actor = null): InventoryTransaction
    {
        return DB::transaction(function () use ($type, $item, $location, $quantity, $occurredOn, $details, $actor) {
            $key = $details['idempotency_key'] ?? null;

            if ($key && ($existing = InventoryTransaction::firstWhere('idempotency_key', $key))) {
                return $this->sameRequest($existing, $type, $item, $location, $quantity, $details)
                    ? $existing
                    : throw new DomainException('This idempotency key was already used for something else.', 'idempotency_conflict');
            }

            // One posting at a time per item keeps balances and layers consistent.
            $item = InventoryItem::lockForUpdate()->findOrFail($item instanceof InventoryItem ? $item->id : $item);
            $location = $location instanceof InventoryLocation ? $location : InventoryLocation::findOrFail($location);
            $batch = $this->batch($item, $details['batch'] ?? null);

            $this->validate($type, $item, $location, $batch, $quantity, $occurredOn);

            $inbound = bccomp($quantity, '0', 3) > 0;
            $value = $inbound ? $this->additionValue($type, $item, $quantity, $details) : -$this->valuation->consume($item->id, $location->id, $batch?->id, ltrim($quantity, '-'));

            $row = InventoryTransaction::create([
                'group_uuid' => $details['group_uuid'] ?? null,
                'type' => $type,
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
                'inventory_batch_id' => $batch?->id,
                'quantity' => $quantity,
                'value_minor' => $value,
                'occurred_on' => $occurredOn,
                'source_type' => $details['source_type'] ?? null,
                'source_id' => $details['source_id'] ?? null,
                'reason' => $details['reason'] ?? null,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $key,
            ]);

            if ($inbound) {
                $this->valuation->addLayer($row, $occurredOn);
            }

            InventoryTransactionPosted::dispatch($row);

            return $row;
        });
    }

    private function batch(InventoryItem $item, InventoryBatch|int|null $batch): ?InventoryBatch
    {
        if ($batch === null) {
            return null;
        }

        $batch = $batch instanceof InventoryBatch ? $batch : InventoryBatch::find($batch);

        return $batch?->inventory_item_id === $item->id ? $batch : throw new DomainException('That batch belongs to a different item.', 'invalid_batch');
    }

    private function validate(InventoryTransactionType $type, InventoryItem $item, InventoryLocation $location, ?InventoryBatch $batch, string $quantity, CarbonInterface $on): void
    {
        if (! preg_match('/^-?\d{1,11}(\.\d{1,3})?$/', $quantity) || bccomp($quantity, '0', 3) === 0) {
            throw new DomainException('The quantity must be a non-zero number with at most 3 decimals.', 'stock_quantity');
        }

        $inbound = bccomp($quantity, '0', 3) > 0;

        if (($type->adds() && ! $inbound) || ($type->removes() && $inbound)) {
            throw new DomainException($type->label().' must '.($type->adds() ? 'add' : 'remove').' stock.', 'stock_direction');
        }

        if (! $item->is_active || ! $location->is_active) {
            throw new DomainException('The item and the store must both be active.', 'inactive_stock_target');
        }

        if ($on->gt(now()->addMinutes(5))) {
            throw new DomainException('Stock cannot be dated in the future.', 'stock_future');
        }

        if ($item->tracks_batches && $batch === null) {
            throw new DomainException("{$item->name} is tracked by batch: choose its batch.", 'batch_required');
        }

        if (! $item->tracks_batches && $batch !== null) {
            throw new DomainException("{$item->name} is not tracked by batch.", 'batch_not_tracked');
        }

        if ($batch && ! $batch->is_active) {
            throw new DomainException("Batch {$batch->batch_number} is not active.", 'inactive_batch');
        }

        if ($batch && $batch->isExpiredOn($on) && ($inbound || $type->blocksExpiredStock())) {
            throw new DomainException("Batch {$batch->batch_number} expired on {$batch->expiry_date->format('d M Y')}.", 'batch_expired');
        }
    }

    /** @param array<string, mixed> $details */
    private function additionValue(InventoryTransactionType $type, InventoryItem $item, string $quantity, array $details): int
    {
        if (isset($details['value_minor'])) {
            $value = (int) $details['value_minor'];
        } elseif (isset($details['unit_cost_minor'])) {
            $value = Ratio::toWhole(bcmul($quantity, (string) (int) $details['unit_cost_minor'], 6));
        } elseif (in_array($type, [InventoryTransactionType::Return, InventoryTransactionType::Adjustment], true)) {
            $value = Ratio::toWhole(bcmul($quantity, (string) $this->valuation->currentUnitCost($item->id), 6));
        } else {
            throw new DomainException('Stock coming in needs a cost.', 'cost_required');
        }

        return $value >= 0 ? $value : throw new DomainException('The cost cannot be negative.', 'cost_negative');
    }

    /** @param array<string, mixed> $details */
    private function sameRequest(InventoryTransaction $existing, InventoryTransactionType $type, InventoryItem|int $item, InventoryLocation|int $location, string $quantity, array $details): bool
    {
        $itemId = $item instanceof InventoryItem ? $item->id : $item;
        $locationId = $location instanceof InventoryLocation ? $location->id : $location;
        $batch = $details['batch'] ?? null;
        $batchId = $batch instanceof InventoryBatch ? $batch->id : $batch;

        return $existing->type === $type && $existing->inventory_item_id === $itemId && $existing->inventory_location_id === $locationId
            && $existing->inventory_batch_id === $batchId && bccomp((string) $existing->quantity, $quantity, 3) === 0;
    }
}
