<?php

namespace App\Domain\Production\Actions;

use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\BatchEventType;
use App\Models\User;
use Carbon\CarbonInterface;

/** A counting correction (e.g. after a physical count). Needs a reason; the UI requires approval rights. */
class AdjustBatchCount
{
    public function __construct(private readonly PostBatchEvent $postEvent) {}

    public function __invoke(ProductionBatch $batch, int $delta, CarbonInterface $on, string $reason, ?User $actor = null): ProductionBatchEvent
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required for a count adjustment.', 'reason_required');
        }

        return ($this->postEvent)($batch, BatchEventType::Adjustment, $delta, $on, ['notes' => trim($reason)], $actor);
    }
}
