<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Meat\Models\MeatProductionLine;
use App\Domain\Production\Actions\GetBatchPerformance;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\MeatProductionStatus;
use App\Support\Ratio;
use Carbon\CarbonInterface;

/**
 * What things cost to make, from the cost data the operational modules already hold:
 *  - pigs:   per batch, total cost / pigs alive (cost per pig) and / live weight on the latest weigh-in (cost per kg live weight)
 *  - feed:   per feed batch produced in the period, total cost / kg made
 *  - semen:  per batch released in the period, doses x the farm's cost per dose
 *  - meat:   per product made in the period, cost carried on its lines / kg
 */
class GetUnitCosts
{
    public function __construct(private readonly GetBatchPerformance $performance, private readonly ResolveSettings $settings) {}

    /** @return array{pigs: list<array<string, mixed>>, feed: list<array<string, mixed>>, semen: list<array<string, mixed>>, meat: list<array<string, mixed>>} */
    public function __invoke(CarbonInterface $from, CarbonInterface $to): array
    {
        return ['pigs' => $this->pigs(), 'feed' => $this->feed($from, $to), 'semen' => $this->semen($from, $to), 'meat' => $this->meat($from, $to)];
    }

    /** @return list<array<string, mixed>> */
    private function pigs(): array
    {
        return ProductionBatch::with('stage')->orderBy('code')->get()->map(function (ProductionBatch $batch) {
            $p = $this->performance->__invoke($batch);
            $surviving = $p['placed'] - $p['mortality'];
            $liveKg = $p['latest_weight_kg'] && $surviving > 0 ? bcmul((string) $p['latest_weight_kg'], (string) $surviving, 2) : null;

            return [
                'batch' => $batch->code, 'name' => $batch->name, 'stage' => $batch->stage?->name, 'pigs' => $surviving, 'total_cost_minor' => $p['total_cost_minor'],
                'cost_per_pig_minor' => $p['cost_per_pig_minor'], 'live_weight_kg' => $liveKg,
                'cost_per_kg_live_minor' => $liveKg && bccomp($liveKg, '0', 2) > 0 ? Ratio::toWhole((string) Ratio::average($p['total_cost_minor'], $liveKg, 4)) : null,
            ];
        })->filter(fn ($r) => $r['total_cost_minor'] > 0)->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function feed(CarbonInterface $from, CarbonInterface $to): array
    {
        return FeedProductionBatch::with('item')->whereNull('reversed_at')->whereDate('produced_on', '>=', $from->toDateString())->whereDate('produced_on', '<=', $to->toDateString())->orderBy('produced_on')->get()
            ->map(fn (FeedProductionBatch $b) => [
                'feed' => $b->item->name ?? '-', 'produced_on' => $b->produced_on, 'output_kg' => $b->output_kg, 'material_cost_minor' => $b->material_cost_minor,
                'other_cost_minor' => $b->other_cost_minor, 'total_cost_minor' => $b->total_cost_minor, 'cost_per_kg_minor' => $b->cost_per_kg_minor,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function semen(CarbonInterface $from, CarbonInterface $to): array
    {
        $perDose = (int) $this->settings->get('semen.cost_per_dose_minor');

        return SemenBatch::with('boar')->whereNotNull('released_at')->whereBetween('released_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('released_at')->get()
            ->map(fn (SemenBatch $b) => [
                'batch' => $b->number, 'boar' => $b->boar->animal_number, 'doses' => (int) $b->doses_produced, 'cost_per_dose_minor' => $perDose, 'total_cost_minor' => (int) $b->doses_produced * $perDose,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function meat(CarbonInterface $from, CarbonInterface $to): array
    {
        $batches = MeatProductionBatch::where('status', MeatProductionStatus::Produced)->whereDate('produced_on', '>=', $from->toDateString())->whereDate('produced_on', '<=', $to->toDateString())->pluck('id');

        return MeatProductionLine::with('product')->whereIn('meat_production_batch_id', $batches)->get()->groupBy('meat_product_id')->map(function ($lines) {
            $kg = (string) $lines->sum('weight_kg');
            $cost = (int) $lines->sum('cost_minor');

            return ['product' => $lines->first()->product->name, 'kg' => bcadd($kg, '0', 2), 'total_cost_minor' => $cost,
                'cost_per_kg_minor' => bccomp($kg, '0', 2) > 0 ? Ratio::toWhole((string) Ratio::average($cost, $kg, 4)) : null];
        })->sortBy('product')->values()->all();
    }
}
