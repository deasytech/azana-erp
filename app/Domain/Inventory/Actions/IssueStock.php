<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLayer;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Takes stock out (consumption, sale, wastage or transfer out). For an item tracked by batch with no batch
 * named, the batches closest to expiry are used first, one ledger line per batch; expired batches are skipped
 * where the type does not allow using them. $details are PostInventoryTransaction's.
 */
class IssueStock
{
    public function __construct(private readonly PostInventoryTransaction $post) {}

    /**
     * @param  string  $quantity  positive amount to take out
     * @param  array<string, mixed>  $details
     * @return Collection<int, InventoryTransaction>
     */
    public function __invoke(InventoryTransactionType $type, InventoryItem|int $item, InventoryLocation|int $location, string $quantity, CarbonInterface $on, array $details = [], ?User $actor = null): Collection
    {
        if (! $type->removes()) {
            throw new DomainException('Choose a type that removes stock.', 'stock_direction');
        }

        if (! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $quantity) || bccomp($quantity, '0', 3) <= 0) {
            throw new DomainException('The quantity must be a positive number with at most 3 decimals.', 'stock_quantity');
        }

        $key = $details['idempotency_key'] ?? null;

        if ($key && ($replay = InventoryTransaction::where('idempotency_key', $key)->orWhere('idempotency_key', 'like', $key.'#%')->orderBy('id')->get())->isNotEmpty()) {
            return $replay;
        }

        return DB::transaction(function () use ($type, $item, $location, $quantity, $on, $details, $actor, $key) {
            $item = InventoryItem::lockForUpdate()->findOrFail($item instanceof InventoryItem ? $item->id : $item);
            $locationId = $location instanceof InventoryLocation ? $location->id : $location;
            $details['group_uuid'] ??= (string) Str::uuid();

            $draws = empty($details['batch']) && $item->tracks_batches
                ? $this->plan($type, $item, $locationId, $quantity, $on)
                : [[$details['batch'] ?? null, $quantity]];

            return collect($draws)->values()->map(fn (array $draw, int $n) => ($this->post)(
                $type, $item, $location, '-'.$draw[1], $on,
                ['batch' => $draw[0], 'idempotency_key' => $key ? ($n === 0 ? $key : $key.'#'.($n + 1)) : null] + $details,
                $actor,
            ));
        });
    }

    /** @return list<array{0: int, 1: string}> batch id and quantity to take from it */
    private function plan(InventoryTransactionType $type, InventoryItem $item, int $locationId, string $quantity, CarbonInterface $on): array
    {
        $held = InventoryLayer::with('batch')->where('inventory_item_id', $item->id)->where('inventory_location_id', $locationId)
            ->where('remaining_quantity', '>', 0)->get()->groupBy('inventory_batch_id')
            ->map(fn (Collection $layers) => ['batch' => $layers->first()->batch, 'quantity' => $layers->reduce(fn (string $sum, $l) => bcadd($sum, (string) $l->remaining_quantity, 3), '0')])
            ->filter(fn (array $row) => ! ($type->blocksExpiredStock() && $row['batch']->isExpiredOn($on)))
            ->sortBy(fn (array $row) => [$row['batch']->expiry_date?->timestamp ?? PHP_INT_MAX, $row['batch']->id])
            ->values();

        $draws = [];
        $left = $quantity;

        foreach ($held as $row) {
            if (bccomp($left, '0', 3) <= 0) {
                break;
            }

            $take = bccomp($left, $row['quantity'], 3) >= 0 ? $row['quantity'] : $left;
            $draws[] = [$row['batch']->id, $take];
            $left = bcsub($left, $take, 3);
        }

        if (bccomp($left, '0', 3) > 0) {
            throw new DomainException("Not enough usable stock of {$item->name}: {$left} short across its unexpired batches.", 'insufficient_stock');
        }

        return $draws;
    }
}
