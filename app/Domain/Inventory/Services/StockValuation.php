<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Models\InventoryLayer;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ValuationMethod;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cost layers behind the ledger. Each receipt is a layer holding what is left of its quantity and value;
 * issues use up layers under the farm's valuation method (FIFO or weighted average). Values are whole
 * minor units; the last unit taken from a layer takes all of its value, so no cost is ever lost to rounding.
 * Only the inventory actions call this, always inside a transaction with the item row locked.
 */
class StockValuation
{
    public function __construct(private readonly ResolveSettings $settings) {}

    public function method(): ValuationMethod
    {
        return ValuationMethod::tryFrom((string) $this->settings->get('inventory.valuation_method')) ?? ValuationMethod::Fifo;
    }

    public function addLayer(InventoryTransaction $receipt, CarbonInterface $receivedOn): InventoryLayer
    {
        return InventoryLayer::create([
            'inventory_transaction_id' => $receipt->id,
            'inventory_item_id' => $receipt->inventory_item_id,
            'inventory_location_id' => $receipt->inventory_location_id,
            'inventory_batch_id' => $receipt->inventory_batch_id,
            'received_on' => $receivedOn,
            'quantity' => $receipt->quantity,
            'remaining_quantity' => $receipt->quantity,
            'remaining_value_minor' => $receipt->value_minor,
        ]);
    }

    /** Quantity on hand in one item/location/batch (batch null = stock without a batch). */
    public function onHand(int $itemId, int $locationId, ?int $batchId): string
    {
        return (string) $this->layers($itemId, $locationId, $batchId)->sum('remaining_quantity');
    }

    /**
     * Takes quantity out of the layers and returns the value (minor units) it carried.
     *
     * @throws DomainException when there is not enough stock
     */
    public function consume(int $itemId, int $locationId, ?int $batchId, string $quantity): int
    {
        $layers = $this->layers($itemId, $locationId, $batchId)->lockForUpdate()->get();
        $available = $layers->reduce(fn (string $sum, InventoryLayer $l) => bcadd($sum, (string) $l->remaining_quantity, 3), '0');

        if (bccomp($available, $quantity, 3) < 0) {
            throw new DomainException("Not enough stock: {$available} available, {$quantity} needed.", 'insufficient_stock');
        }

        return $this->method() === ValuationMethod::WeightedAverage
            ? $this->consumeAtAverage($layers, $available, $quantity)
            : $this->consumeInOrder($layers, $quantity);
    }

    /** Value per unit of what is on hand across all locations and batches of an item (0 when nothing is held). */
    public function currentUnitCost(int $itemId): int
    {
        $layers = InventoryLayer::where('inventory_item_id', $itemId)->where('remaining_quantity', '>', 0)->get();
        $quantity = $layers->reduce(fn (string $sum, InventoryLayer $l) => bcadd($sum, (string) $l->remaining_quantity, 3), '0');

        if (bccomp($quantity, '0', 3) > 0) {
            return Ratio::toWhole(bcdiv((string) $layers->sum('remaining_value_minor'), $quantity, 6));
        }

        $last = InventoryTransaction::where('inventory_item_id', $itemId)->where('quantity', '>', 0)->orderByDesc('id')->first();

        return $last ? Ratio::toWhole(bcdiv((string) $last->value_minor, (string) $last->quantity, 6)) : 0;
    }

    /** Takes back exactly one receipt's layer (used when a receipt is reversed). */
    public function removeReceiptLayer(InventoryTransaction $receipt): void
    {
        $layer = InventoryLayer::where('inventory_transaction_id', $receipt->id)->lockForUpdate()->first();

        if (! $layer || bccomp((string) $layer->remaining_quantity, (string) $layer->quantity, 3) !== 0) {
            throw new DomainException('Part of this receipt has already been used, so it cannot be reversed.', 'receipt_in_use');
        }

        $layer->update(['remaining_quantity' => 0, 'remaining_value_minor' => 0]);
    }

    private function layers(int $itemId, int $locationId, ?int $batchId)
    {
        return InventoryLayer::where('inventory_item_id', $itemId)
            ->where('inventory_location_id', $locationId)
            ->when($batchId === null, fn ($q) => $q->whereNull('inventory_batch_id'), fn ($q) => $q->where('inventory_batch_id', $batchId))
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_on')->orderBy('id');
    }

    /** @param Collection<int, InventoryLayer> $layers */
    private function consumeInOrder($layers, string $quantity): int
    {
        $value = 0;
        $left = $quantity;

        foreach ($layers as $layer) {
            if (bccomp($left, '0', 3) <= 0) {
                break;
            }

            $remaining = (string) $layer->remaining_quantity;
            $take = bccomp($left, $remaining, 3) >= 0 ? $remaining : $left;
            $taken = bccomp($take, $remaining, 3) === 0
                ? $layer->remaining_value_minor
                : Ratio::toWhole(bcdiv(bcmul((string) $layer->remaining_value_minor, $take, 6), $remaining, 6));

            $layer->update([
                'remaining_quantity' => bcsub($remaining, $take, 3),
                'remaining_value_minor' => $layer->remaining_value_minor - $taken,
            ]);

            $value += $taken;
            $left = bcsub($left, $take, 3);
        }

        return $value;
    }

    /** @param Collection<int, InventoryLayer> $layers */
    private function consumeAtAverage($layers, string $available, string $quantity): int
    {
        $total = (int) $layers->sum('remaining_value_minor');
        $value = bccomp($quantity, $available, 3) === 0
            ? $total
            : Ratio::toWhole(bcdiv(bcmul((string) $total, $quantity, 6), $available, 6));

        // Use the quantity up in order, then spread the value that is left over what remains in proportion.
        $left = $quantity;
        $remainingLayers = [];

        foreach ($layers as $layer) {
            $remaining = (string) $layer->remaining_quantity;

            if (bccomp($left, '0', 3) > 0) {
                $take = bccomp($left, $remaining, 3) >= 0 ? $remaining : $left;
                $remaining = bcsub($remaining, $take, 3);
                $left = bcsub($left, $take, 3);
            }

            $remainingLayers[] = [$layer, $remaining];
        }

        $valueLeft = $total - $value;
        $quantityLeft = bcsub($available, $quantity, 3);
        $lastIndex = count(array_filter($remainingLayers, fn ($pair) => bccomp($pair[1], '0', 3) > 0)) - 1;
        $index = 0;

        foreach ($remainingLayers as [$layer, $remaining]) {
            $holds = bccomp($remaining, '0', 3) > 0;
            $share = ! $holds ? 0 : ($index === $lastIndex ? $valueLeft : Ratio::toWhole(bcdiv(bcmul((string) ($total - $value), $remaining, 6), $quantityLeft, 6)));

            $layer->update(['remaining_quantity' => $remaining, 'remaining_value_minor' => $share]);
            $valueLeft -= $share;
            $index += $holds ? 1 : 0;
        }

        return $value;
    }
}
