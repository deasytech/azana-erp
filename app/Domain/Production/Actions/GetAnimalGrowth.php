<?php

namespace App\Domain\Production\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Support\Ratio;

/** Growth of one tracked animal from its valid (non-voided) weight records, and the feed it was recorded against. */
class GetAnimalGrowth
{
    /** @return array<string, int|string|null> */
    public function __invoke(Animal $animal): array
    {
        $weights = $animal->weights()->whereNull('voided_at')->reorder('weighed_at')->get();
        $empty = ['weights' => $weights->count(), 'first_weight_kg' => null, 'latest_weight_kg' => null, 'days' => null, 'gain_kg' => null, 'adg_kg' => null, 'feed_kg' => null, 'fcr' => null];

        if ($weights->count() < 2) {
            return $empty;
        }

        [$first, $last] = [$weights->first(), $weights->last()];
        $days = (int) $first->weighed_at->startOfDay()->diffInDays($last->weighed_at->startOfDay());
        $gain = bcsub((string) $last->weight_kg, (string) $first->weight_kg, 2);
        $feed = (string) (FeedConsumptionRecord::where('animal_id', $animal->id)->whereNull('voided_at')
            ->whereDate('consumed_on', '>', $first->weighed_at->toDateString())->whereDate('consumed_on', '<=', $last->weighed_at->toDateString())->sum('quantity_kg') ?: '0');

        return [
            'weights' => $weights->count(),
            'first_weight_kg' => $first->weight_kg,
            'latest_weight_kg' => $last->weight_kg,
            'days' => $days,
            'gain_kg' => $gain,
            'adg_kg' => $days > 0 ? Ratio::average($gain, $days, 3) : null,
            'feed_kg' => bcadd($feed, '0', 2),
            'fcr' => bccomp($gain, '0', 2) > 0 && bccomp($feed, '0', 2) > 0 ? Ratio::average($feed, $gain, 2) : null,
        ];
    }
}
