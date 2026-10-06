<?php

namespace App\Domain\Slaughter\Actions;

use App\Domain\Slaughter\Models\SlaughterBatch;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\SlaughterBatchStatus as Status;
use App\Enums\SlaughterRecordStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Slaughter days: scheduled, worked through, then closed once every pig has been dealt with. */
class ManageSlaughterBatch
{
    public function __construct(private readonly NextNumber $nextNumber) {}

    public function schedule(CarbonInterface $on, ?string $notes = null, ?User $actor = null): SlaughterBatch
    {
        if ($on->copy()->startOfDay()->lt(now()->subDays(7)->startOfDay())) {
            throw new DomainException('A slaughter day cannot be scheduled more than a week in the past.', 'schedule_date');
        }

        return DB::transaction(fn () => SlaughterBatch::create([
            'number' => sprintf('SB-%06d', ($this->nextNumber)('slaughter_batch')),
            'scheduled_on' => $on,
            'notes' => $notes,
            'created_by' => ($actor ?? Auth::user())?->getKey(),
        ]));
    }

    /** Cancels a day nothing has been slaughtered on; pigs already received are sent back. */
    public function cancel(SlaughterBatch $batch, string $reason): SlaughterBatch
    {
        trim($reason) !== '' || throw new DomainException('A reason is required to cancel a slaughter day.', 'reason_required');

        return DB::transaction(function () use ($batch, $reason) {
            $batch = SlaughterBatch::lockForUpdate()->findOrFail($batch->id);
            $batch->isOpen() || throw new DomainException("{$batch->number} is {$batch->status->label()} and cannot be cancelled.", 'batch_state');

            if ($batch->records()->whereIn('status', [SlaughterRecordStatus::Slaughtered, SlaughterRecordStatus::Condemned])->exists()) {
                throw new DomainException('Pigs have already been slaughtered on this day, so it cannot be cancelled.', 'batch_slaughtered');
            }

            $batch->records()->where('status', SlaughterRecordStatus::Received)->update(['status' => SlaughterRecordStatus::Rejected, 'ante_mortem_notes' => 'Slaughter day cancelled: '.trim($reason)]);
            $batch->update(['status' => Status::Cancelled, 'notes' => trim(($batch->notes ? $batch->notes."\n" : '').'Cancelled: '.trim($reason))]);

            return $batch;
        });
    }

    /** Closes a day once nothing is left waiting to be slaughtered. */
    public function complete(SlaughterBatch $batch): SlaughterBatch
    {
        return DB::transaction(function () use ($batch) {
            $batch = SlaughterBatch::lockForUpdate()->findOrFail($batch->id);
            $batch->isOpen() || throw new DomainException("{$batch->number} is {$batch->status->label()}.", 'batch_state');

            if (! $batch->records()->exists()) {
                throw new DomainException('Nothing was received on this slaughter day.', 'batch_empty');
            }

            if ($batch->records()->where('status', SlaughterRecordStatus::Received)->exists()) {
                throw new DomainException('Some pigs are still waiting to be slaughtered (or sent back).', 'batch_pending');
            }

            $batch->update(['status' => Status::Completed]);

            return $batch;
        });
    }
}
