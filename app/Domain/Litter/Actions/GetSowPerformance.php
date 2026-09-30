<?php

namespace App\Domain\Litter\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Actions\GetSowStatus;
use App\Domain\Litter\Models\Litter;
use App\Support\Ratio;
use Illuminate\Support\Collection;

/** Lifetime reproductive performance of one sow, aggregated over all her litters. */
class GetSowPerformance
{
    public function __construct(private readonly GetSowStatus $status, private readonly GetLitterKpis $litterKpis) {}

    /** @return array<string, int|string|null> */
    public function __invoke(Animal $sow): array
    {
        $litters = Litter::with(['farrowing', 'weaning', 'service'])->where('sow_id', $sow->id)->orderBy('born_on')->get();
        $n = $litters->count();

        $totalBorn = $litters->sum(fn ($l) => $l->farrowing->total_born);
        $alive = $litters->sum(fn ($l) => $l->farrowing->born_alive);
        $losses = $litters->sum(fn ($l) => (int) $l->losses()->sum('count'));
        $weanedLitters = $litters->filter(fn ($l) => $l->weaning);
        $weaned = $weanedLitters->sum(fn ($l) => $l->weaning->weaned_count);
        $aliveInWeaned = $weanedLitters->sum(fn ($l) => $l->farrowing->born_alive);
        $birthWeights = $litters->map(fn ($l) => ($this->litterKpis)($l)['avg_birth_weight_kg'])->filter();

        return [
            'status' => $this->status->__invoke($sow)?->value,
            'parity' => $n,
            'total_born' => $totalBorn,
            'avg_total_born' => Ratio::average($totalBorn, $n),
            'avg_born_alive' => Ratio::average($alive, $n),
            'avg_weaned' => Ratio::average($weaned, $weanedLitters->count()),
            'total_weaned' => $weaned,
            'pre_weaning_mortality_percent' => Ratio::percent($losses, $alive),
            'weaning_percent' => Ratio::percent($weaned, $aliveInWeaned),
            'avg_birth_weight_kg' => Ratio::average($birthWeights->reduce(fn ($sum, $w) => bcadd($sum, $w, 2), '0'), $birthWeights->count()),
            'avg_farrowing_interval_days' => $this->averageInterval($litters),
        ];
    }

    /** @param Collection<int, Litter> $litters */
    private function averageInterval($litters): ?string
    {
        $dates = $litters->pluck('born_on')->values();

        if ($dates->count() < 2) {
            return null;
        }

        $days = $dates->slice(1)->values()->sum(fn ($date, $i) => $dates[$i]->diffInDays($date));

        return Ratio::average($days, $dates->count() - 1);
    }
}
