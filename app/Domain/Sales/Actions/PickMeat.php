<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Meat\Models\MeatProductionLine;
use App\Domain\Sales\Services\StockAvailability;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\MeatProductionStatus;

/**
 * Picks meat for an order: the lots of a product in a cold room with the earliest use-by date first, as many as it takes,
 * counting only what is free (on hand less what other orders hold) and has not passed its use-by date.
 */
class PickMeat
{
    public function __construct(private readonly StockAvailability $availability) {}

    /** @return list<array{lot: MeatProductionLine, kg: string}> */
    public function __invoke(MeatProduct $product, int $locationId, string $kg): array
    {
        $lots = MeatProductionLine::with(['batch', 'inventoryBatch', 'product'])
            ->where('meat_product_id', $product->id)->whereDate('use_by', '>=', now()->startOfDay())
            ->whereHas('batch', fn ($q) => $q->where('status', MeatProductionStatus::Produced))
            ->orderBy('use_by')->orderBy('id')->get()->filter(fn (MeatProductionLine $l) => $l->inventoryBatch?->is_active);

        $picks = [];
        $left = $kg;
        $free = '0';

        foreach ($lots as $lot) {
            $here = $this->availability->free($product->inventory_item_id, $locationId, $lot->inventory_batch_id);
            $free = bcadd($free, max($here, '0'), 3);

            if (bccomp($left, '0', 3) > 0 && bccomp($here, '0', 3) > 0) {
                $take = bccomp($here, $left, 3) >= 0 ? $left : $here;
                $picks[] = ['lot' => $lot, 'kg' => bcadd($take, '0', 3)];
                $left = bcsub($left, $take, 3);
            }
        }

        if (bccomp($left, '0', 3) > 0) {
            throw new DomainException("Only {$free} kg of {$product->name} is free in that cold room (the rest is reserved, expired or not there), {$kg} kg needed.", 'insufficient_meat');
        }

        return $picks;
    }
}
