<?php

namespace App\Domain\Meat\Actions;

use App\Domain\Meat\Models\MeatProductionBatch;
use Illuminate\Support\Collection;

/** Where a meat batch came from: the carcasses, the slaughter records and the animals or production batches behind them. */
class GetMeatTrace
{
    /** @return Collection<int, array{carcass: string, slaughter_day: string, animal: ?string, production_batch: ?string, heads: int, live_kg: string, hot_kg: string, dressing_percent: string}> */
    public function __invoke(MeatProductionBatch $batch): Collection
    {
        return $batch->carcasses()->with('record.batch', 'record.animal', 'record.productionBatch')->orderBy('id')->get()->map(fn ($c) => [
            'carcass' => $c->number,
            'slaughter_day' => $c->record->batch->number,
            'animal' => $c->record->animal?->animal_number,
            'production_batch' => $c->record->productionBatch?->code,
            'heads' => $c->record->heads,
            'live_kg' => (string) $c->live_weight_kg,
            'hot_kg' => (string) $c->hot_weight_kg,
            'dressing_percent' => (string) $c->dressing_percent,
        ]);
    }
}
