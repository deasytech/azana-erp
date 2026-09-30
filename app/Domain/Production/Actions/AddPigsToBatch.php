<?php

namespace App\Domain\Production\Actions;

use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\BatchEventType;
use App\Models\User;
use Carbon\CarbonInterface;

/** Adds untracked pigs to a batch (more placements, or pigs transferred in from another batch). */
class AddPigsToBatch
{
    public function __construct(private readonly PostBatchEvent $postEvent) {}

    public function __invoke(ProductionBatch $batch, int $count, CarbonInterface $on, BatchEventType $type = BatchEventType::Placement, ?int $unitCostMinor = null, ?string $notes = null, ?User $actor = null, ?string $idempotencyKey = null): ProductionBatchEvent
    {
        if (! $type->adds()) {
            throw new DomainException('Choose a placement or a transfer in.', 'batch_event_type');
        }

        return ($this->postEvent)($batch, $type, $count, $on, [
            'unit_cost_minor' => $unitCostMinor, 'notes' => $notes, 'idempotency_key' => $idempotencyKey,
        ], $actor);
    }
}
