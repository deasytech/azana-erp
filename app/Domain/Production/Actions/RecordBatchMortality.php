<?php

namespace App\Domain\Production\Actions;

use App\Domain\Farm\Models\LookupValue;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\BatchEventType;
use App\Enums\LookupCategory;
use App\Models\User;
use Carbon\CarbonInterface;

/** Records deaths of untracked pigs: they leave the batch count and feed mortality analysis. */
class RecordBatchMortality
{
    public function __construct(private readonly PostBatchEvent $postEvent) {}

    public function __invoke(ProductionBatch $batch, int $count, CarbonInterface $on, int $causeId, ?string $notes = null, ?User $actor = null, ?string $idempotencyKey = null): ProductionBatchEvent
    {
        LookupValue::where('category', LookupCategory::MortalityCause->value)->where('is_active', true)->whereKey($causeId)->exists()
            || throw new DomainException('Choose a valid cause of death.', 'invalid_cause');

        return ($this->postEvent)($batch, BatchEventType::Mortality, -$count, $on, [
            'cause_id' => $causeId, 'notes' => $notes, 'idempotency_key' => $idempotencyKey,
        ], $actor);
    }
}
