<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Semen\Models\SemenBatch;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Doses in stock told apart by breed, boar, batch and expiry. */
class GetSemenStock
{
    public function __construct(private readonly GetStockLevels $levels) {}

    /** @return Collection<int, array{breed: ?string, boar: string, batch: string, expiry_date: CarbonInterface, doses: string, store: string, status: string, sellable: bool, semen_batch: SemenBatch}> */
    public function __invoke(): Collection
    {
        $rows = ($this->levels)()->filter(fn ($row) => $row->item->breed_id !== null && $row->inventory_batch_id !== null);
        $batches = SemenBatch::with(['boar', 'breed', 'inventoryBatch'])->whereIn('inventory_batch_id', $rows->pluck('inventory_batch_id'))->get()->keyBy('inventory_batch_id');

        return $rows->filter(fn ($row) => $batches->has($row->inventory_batch_id))->map(function ($row) use ($batches) {
            $batch = $batches[$row->inventory_batch_id];

            return [
                'breed' => $batch->breed?->name,
                'boar' => $batch->boar->animal_number,
                'batch' => $batch->number,
                'expiry_date' => $batch->expiry_date,
                'doses' => $row->on_hand,
                'store' => $row->location->name,
                'status' => $batch->status->label(),
                'sellable' => $batch->isSellable(),
                'semen_batch' => $batch,
            ];
        })->sortBy(fn (array $r) => [$r['expiry_date']->timestamp, $r['batch']])->values();
    }
}
