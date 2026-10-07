<?php

namespace App\Domain\Meat\Actions;

use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Meat\Models\MeatProductionLine;
use App\Enums\InventoryCategory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Meat in the cold rooms by product, batch and use-by date, with what it cost. */
class GetMeatStock
{
    public function __construct(private readonly GetStockLevels $levels) {}

    /** @return Collection<int, array{line_id: int, product_id: int, location_id: int, product: string, batch: string, use_by: CarbonInterface, kg: string, value_minor: int, store: string, expired: bool, production_batch_id: int}> */
    public function __invoke(): Collection
    {
        $rows = ($this->levels)()->filter(fn ($row) => $row->item->category === InventoryCategory::Meat && $row->inventory_batch_id !== null);
        $lines = MeatProductionLine::with(['product', 'batch'])->whereIn('inventory_batch_id', $rows->pluck('inventory_batch_id'))->get();

        return $rows->map(function ($row) use ($lines) {
            $line = $lines->first(fn (MeatProductionLine $l) => $l->inventory_batch_id === $row->inventory_batch_id);

            return $line ? [
                'line_id' => $line->id,
                'product_id' => $line->meat_product_id,
                'location_id' => $row->inventory_location_id,
                'product' => $line->product->name,
                'batch' => $row->batch->batch_number,
                'use_by' => $line->use_by,
                'kg' => $row->on_hand,
                'value_minor' => $row->value_minor,
                'store' => $row->location->name,
                'expired' => $line->use_by->lt(now()->startOfDay()),
                'production_batch_id' => $line->meat_production_batch_id,
            ] : null;
        })->filter()->sortBy(fn (array $r) => [$r['use_by']->timestamp, $r['product']])->values();
    }
}
