<?php

namespace App\Domain\Production\Actions;

use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionCost;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ProductionCostCategory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

/** Attributes a cost (medicine, labour, transport...) to a batch. Feed cost comes from feed consumption records. */
class RecordProductionCost
{
    public function __invoke(ProductionBatch $batch, CarbonInterface $incurredOn, ProductionCostCategory $category, int $amountMinor, string $description, ?User $actor = null): ProductionCost
    {
        if ($amountMinor < 1 || trim($description) === '') {
            throw new DomainException('A cost needs a positive amount and a description.', 'cost_invalid');
        }

        if ($incurredOn->gt(now()->addMinutes(5)) || $incurredOn->lt($batch->started_on)) {
            throw new DomainException('The cost must be dated between the batch start and today.', 'cost_date');
        }

        return $batch->costs()->create([
            'incurred_on' => $incurredOn,
            'category' => $category,
            'amount_minor' => $amountMinor,
            'description' => trim($description),
            'user_id' => ($actor ?? Auth::user())?->getKey(),
        ]);
    }
}
