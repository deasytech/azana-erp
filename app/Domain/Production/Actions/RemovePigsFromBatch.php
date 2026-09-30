<?php

namespace App\Domain\Production\Actions;

use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\BatchEventType;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Removes untracked pigs that left by sale, slaughter, culling or transfer. Deaths use
 * RecordBatchMortality so they carry a cause. Sales and slaughter flows (Phases 11-12) call this too.
 */
class RemovePigsFromBatch
{
    private const ALLOWED = [BatchEventType::Sale, BatchEventType::Slaughter, BatchEventType::Cull, BatchEventType::TransferOut];

    public function __construct(private readonly PostBatchEvent $postEvent) {}

    public function __invoke(ProductionBatch $batch, BatchEventType $type, int $count, CarbonInterface $on, ?string $notes = null, ?User $actor = null, ?string $idempotencyKey = null): ProductionBatchEvent
    {
        if (! in_array($type, self::ALLOWED, true)) {
            throw new DomainException('Choose sale, slaughter, cull or transfer out (record deaths as mortality).', 'batch_event_type');
        }

        return ($this->postEvent)($batch, $type, -$count, $on, ['notes' => $notes, 'idempotency_key' => $idempotencyKey], $actor);
    }
}
