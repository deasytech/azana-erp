<?php

namespace App\Domain\Production\Actions;

use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\BatchEventType;
use App\Enums\BatchStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only way head counts change: appends a signed event to the batch ledger. Counts can never go
 * negative (today or on the event's own date), and a batch closes itself when its last pig leaves.
 */
class PostBatchEvent
{
    /** @param array{animal_id?: ?int, cause_id?: ?int, unit_cost_minor?: ?int, notes?: ?string, idempotency_key?: ?string} $details */
    public function __invoke(ProductionBatch $batch, BatchEventType $type, int $delta, CarbonInterface $occurredOn, array $details = [], ?User $actor = null): ProductionBatchEvent
    {
        $this->assertSign($type, $delta);

        return DB::transaction(function () use ($batch, $type, $delta, $occurredOn, $details, $actor) {
            $batch = ProductionBatch::lockForUpdate()->findOrFail($batch->id);

            if ($key = $details['idempotency_key'] ?? null) {
                $existing = ProductionBatchEvent::firstWhere('idempotency_key', $key);

                if ($existing) {
                    return $existing->production_batch_id === $batch->id
                        ? $existing
                        : throw new DomainException('This idempotency key was already used for a different batch.', 'idempotency_conflict');
                }
            }

            $this->assertAllowed($batch, $delta, $occurredOn);

            $event = $batch->events()->create([
                'type' => $type,
                'delta' => $delta,
                'occurred_on' => $occurredOn,
                'animal_id' => $details['animal_id'] ?? null,
                'cause_id' => $details['cause_id'] ?? null,
                'unit_cost_minor' => $details['unit_cost_minor'] ?? null,
                'notes' => $details['notes'] ?? null,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $key ?? null,
            ]);

            if ($batch->headCount() === 0) {
                $batch->forceFill(['status' => BatchStatus::Closed, 'closed_on' => $occurredOn])->save();
            }

            return $event;
        });
    }

    private function assertSign(BatchEventType $type, int $delta): void
    {
        if ($delta === 0 || ($type->adds() && $delta < 0) || ($type->removes() && $delta > 0)) {
            throw new DomainException("A {$type->label()} must ".($type->adds() ? 'add' : 'remove').' at least one pig.', 'batch_event_delta');
        }
    }

    private function assertAllowed(ProductionBatch $batch, int $delta, CarbonInterface $on): void
    {
        if (! $batch->isActive()) {
            throw new DomainException("Batch {$batch->code} is closed.", 'batch_closed');
        }

        if ($on->gt(now()->addMinutes(5)) || $on->lt($batch->started_on)) {
            throw new DomainException('The date must fall between the batch start and today.', 'batch_event_date');
        }

        if ($delta < 0) {
            $asOf = (int) $batch->events()->whereDate('occurred_on', '<=', $on->toDateString())->sum('delta');

            if ($asOf + $delta < 0 || $batch->headCount() + $delta < 0) {
                throw new DomainException("Batch {$batch->code} does not have ".abs($delta).' pigs to remove on that date.', 'batch_not_enough_pigs');
            }
        }
    }
}
