<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Concerns\ConvertsFeedQuantities;
use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Inventory\Services\StockValuation;
use App\Support\Ratio;

/**
 * What a formula costs to make at today's ingredient costs (what is on hand, else the last receipt), per kg of
 * finished feed (allowing for process loss), per bag and per tonne. Minor currency units.
 */
class GetFormulaCost
{
    use ConvertsFeedQuantities;

    public function __construct(private readonly StockValuation $valuation, private readonly ResolveSettings $settings) {}

    /** @return array{per_kg_minor: int, per_bag_minor: int, per_tonne_minor: int, bag_kg: string, lines: list<array{item_id: int, item: string, kg_per_tonne: string, unit_cost_minor: int, cost_per_kg_minor: string}>, unpriced: list<string>} */
    public function __invoke(FeedFormula $formula): array
    {
        $inputPerKg = $this->inputKg('1', (string) $formula->process_loss_percent);
        $total = '0';
        $lines = [];
        $unpriced = [];

        foreach ($formula->items()->with('item.unit')->get() as $line) {
            $kg = bcdiv(bcmul($inputPerKg, (string) $line->inclusion_percent, 10), '100', 10);
            $unitCost = $this->valuation->currentUnitCost($line->item->id);
            $units = bcmul($kg, UnitOfMeasure::firstWhere('code', 'KG')->convertTo('1', $line->item->unit), 10);
            $cost = bcmul($units, (string) $unitCost, 10);

            $unitCost === 0 && $unpriced[] = $line->item->name;
            $total = bcadd($total, $cost, 10);
            $lines[] = [
                'item_id' => $line->item->id,
                'item' => $line->item->name,
                'kg_per_tonne' => Ratio::average(bcmul($kg, '1000', 10), '1', 3),
                'unit_cost_minor' => $unitCost,
                'cost_per_kg_minor' => Ratio::average($cost, '1', 2),
            ];
        }

        $bagKg = (string) $this->settings->get('feed.bag_weight_kg');

        return [
            'per_kg_minor' => Ratio::toWhole($total),
            'per_bag_minor' => Ratio::toWhole(bcmul($total, $bagKg, 10)),
            'per_tonne_minor' => Ratio::toWhole(bcmul($total, '1000', 10)),
            'bag_kg' => $bagKg,
            'lines' => $lines,
            'unpriced' => $unpriced,
        ];
    }
}
