<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\SalesOrder;
use App\Enums\SalesLineKind;
use Illuminate\Support\Collection;

/** What to fetch, and from where, to fill an order: the batch, store and quantity for every line. */
class GetPickingList
{
    /** @return Collection<int, array{what: string, from: string, batch: ?string, use_by: ?string, quantity: string}> */
    public function __invoke(SalesOrder $order): Collection
    {
        return $order->lines()->with(['semenBatch.boar', 'animal.currentPen', 'productionBatch', 'meatLine.product', 'meatLine.batch', 'location'])->orderBy('id')->get()->map(fn ($line) => match ($line->kind) {
            SalesLineKind::Semen => [
                'what' => 'Semen doses', 'from' => $line->location->name, 'batch' => $line->semenBatch->number,
                'use_by' => $line->semenBatch->expiry_date->format('d M Y'), 'quantity' => (int) $line->quantity.' doses',
            ],
            SalesLineKind::Meat => [
                'what' => $line->meatLine->product->name, 'from' => $line->location->name, 'batch' => $line->meatLine->batch->number,
                'use_by' => $line->meatLine->use_by->format('d M Y'), 'quantity' => $line->quantity.' kg',
            ],
            SalesLineKind::PigAnimal => [
                'what' => "Pig {$line->animal->animal_number}", 'from' => $line->animal->currentPen?->code ?? 'Not in a pen', 'batch' => null, 'use_by' => null,
                'quantity' => $line->unit === 'kg' ? $line->quantity.' kg live' : '1 head',
            ],
            SalesLineKind::PigBatch => [
                'what' => "Pigs from {$line->productionBatch->code}", 'from' => $line->productionBatch->name, 'batch' => $line->productionBatch->code, 'use_by' => null,
                'quantity' => "{$line->heads} head",
            ],
        });
    }
}
