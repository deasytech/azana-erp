<?php

namespace App\Domain\Litter\Actions;

use App\Domain\Litter\Models\Litter;
use App\Support\Ratio;

/** Litter performance from its farrowing, losses and weaning (percentages/averages are exact decimal strings). */
class GetLitterKpis
{
    /** @return array<string, int|string|null> */
    public function __invoke(Litter $litter): array
    {
        $litter->loadMissing(['farrowing', 'weaning', 'service']);
        $f = $litter->farrowing;
        $losses = (int) $litter->losses()->sum('count');
        $weaned = $litter->weaning;

        return [
            'total_born' => $f->total_born,
            'born_alive' => $f->born_alive,
            'stillborn' => $f->stillborn,
            'mummified' => $f->mummified,
            'stillborn_percent' => Ratio::percent($f->stillborn, $f->total_born),
            'pre_weaning_losses' => $losses,
            'pre_weaning_mortality_percent' => Ratio::percent($losses, $f->born_alive),
            'weaned' => $weaned?->weaned_count,
            'weaning_percent' => $weaned ? Ratio::percent($weaned->weaned_count, $f->born_alive) : null,
            'avg_birth_weight_kg' => $this->averageBirthWeight($litter),
            'avg_weaning_weight_kg' => $weaned ? Ratio::average($weaned->total_weight_kg, $weaned->weaned_count) : null,
            'gestation_days' => $litter->service ? (int) $litter->service->serviced_on->diffInDays($litter->born_on) : null,
            'lactation_days' => $weaned ? (int) $litter->born_on->diffInDays($weaned->weaned_on) : null,
        ];
    }

    private function averageBirthWeight(Litter $litter): ?string
    {
        if ($litter->farrowing->total_birth_weight_kg !== null) {
            return Ratio::average($litter->farrowing->total_birth_weight_kg, $litter->farrowing->born_alive);
        }

        $weights = $litter->piglets()->whereNotNull('birth_weight_kg')->pluck('birth_weight_kg');

        return Ratio::average($weights->reduce(fn ($sum, $w) => bcadd($sum, (string) $w, 2), '0'), $weights->count());
    }
}
