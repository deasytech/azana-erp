<?php

namespace App\Domain\Slaughter\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Production\Actions\GetBatchPerformance;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchAnimal;

/**
 * What a pig cost to raise, in minor units - the cost that becomes the cost of its meat. For pigs from a batch it is the
 * batch's cost per pig (entry, feed and other costs); for a tracked animal it is the feed recorded against that animal
 * plus, when it belongs to a batch, the batch's cost per pig.
 */
class GetLiveCost
{
    public function __construct(private readonly GetBatchPerformance $performance) {}

    public function forBatch(ProductionBatch $batch, int $heads): int
    {
        return ($this->performance)($batch)['cost_per_pig_minor'] * $heads;
    }

    public function forAnimal(Animal $animal): int
    {
        $own = (int) FeedConsumptionRecord::where('animal_id', $animal->id)->whereNull('voided_at')->sum('cost_minor');
        $membership = ProductionBatchAnimal::with('batch')->where('animal_id', $animal->id)->whereNull('left_on')->first();

        return $own + ($membership ? $this->forBatch($membership->batch, 1) : 0);
    }
}
