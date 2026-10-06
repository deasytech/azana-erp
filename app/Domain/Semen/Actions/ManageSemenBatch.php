<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Semen\Concerns\WritesOffSemenStock;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\System\Actions\AssertMayDecide;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\Module;
use App\Enums\SemenBatchStatus as Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * What can happen to a batch after it exists: quarantine it (blocking its stock), clear the quarantine with
 * approval, or destroy it (writing off what is left). Release is `ReleaseSemenBatch`; expiry is `ExpireSemenBatches`.
 */
class ManageSemenBatch
{
    use WritesOffSemenStock;

    public function __construct(private readonly AssertMayDecide $mayDecide) {}

    /** Holds a batch back (for example after a suspect result or a recall); stock already released cannot be used meanwhile. */
    public function quarantine(SemenBatch $batch, string $reason): SemenBatch
    {
        $this->requireReason($reason);

        return DB::transaction(function () use ($batch, $reason) {
            $batch = $this->lock($batch);

            if (! in_array($batch->status, [Status::PendingQc, Status::Passed, Status::Released], true)) {
                throw new DomainException("{$batch->number} is {$batch->status->label()} and cannot be quarantined.", 'batch_state');
            }

            $batch->update(['quarantined_from' => $batch->status->value, 'status' => Status::Quarantined, 'status_reason' => trim($reason)]);
            $batch->inventoryBatch?->update(['is_active' => false]);

            return $batch;
        });
    }

    /** Puts a quarantined batch back where it was; needs semen.approve. A batch that expired meanwhile is expired instead. */
    public function clearQuarantine(SemenBatch $batch, User $approver, ?string $notes = null): SemenBatch
    {
        return DB::transaction(function () use ($batch, $approver, $notes) {
            $batch = $this->lock($batch);

            if ($batch->status !== Status::Quarantined) {
                throw new DomainException("{$batch->number} is not in quarantine.", 'batch_state');
            }

            ($this->mayDecide)($approver, null, Module::Semen, 'quarantine release');

            if ($batch->isExpired()) {
                throw new DomainException("{$batch->number} has expired, so it cannot go back into use.", 'batch_expired');
            }

            $back = Status::from($batch->quarantined_from);
            $batch->update(['status' => $back, 'quarantined_from' => null, 'status_reason' => $notes]);

            if ($back === Status::Released) {
                $batch->inventoryBatch?->update(['is_active' => true]);
            }

            return $batch;
        });
    }

    /** Destroys a batch for good: whatever doses are still in stock are written off. */
    public function destroy(SemenBatch $batch, string $reason, ?User $actor = null): SemenBatch
    {
        $this->requireReason($reason);

        return DB::transaction(function () use ($batch, $reason, $actor) {
            $batch = $this->lock($batch);

            if (! $batch->status->isOpen()) {
                throw new DomainException("{$batch->number} is already {$batch->status->label()}.", 'batch_state');
            }

            $this->writeOff($batch, "Destroyed {$batch->number}: ".trim($reason), $actor);
            $batch->update(['status' => Status::Destroyed, 'status_reason' => trim($reason)]);

            return $batch;
        });
    }

    private function lock(SemenBatch $batch): SemenBatch
    {
        return SemenBatch::lockForUpdate()->with('inventoryBatch')->findOrFail($batch->id);
    }

    private function requireReason(string $reason): void
    {
        trim($reason) !== '' || throw new DomainException('A reason is required.', 'reason_required');
    }
}
