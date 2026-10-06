<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Semen\Concerns\WritesOffSemenStock;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\SemenBatchStatus as Status;
use Illuminate\Support\Facades\DB;

/** Marks batches past their expiry date as expired and writes off the doses left. Safe to run as often as you like. */
class ExpireSemenBatches
{
    use WritesOffSemenStock;

    /** @return int how many batches were expired */
    public function __invoke(): int
    {
        $due = SemenBatch::whereIn('status', [Status::PendingQc, Status::Passed, Status::Released, Status::Quarantined])
            ->whereDate('expiry_date', '<', now()->startOfDay())->pluck('id');

        foreach ($due as $id) {
            DB::transaction(function () use ($id) {
                $batch = SemenBatch::lockForUpdate()->with('inventoryBatch')->find($id);

                if (! $batch || ! $batch->isExpired() || ! in_array($batch->status, [Status::PendingQc, Status::Passed, Status::Released, Status::Quarantined], true)) {
                    return;
                }

                $this->writeOff($batch, "Expired {$batch->number}");
                $batch->update(['status' => Status::Expired]);
            });
        }

        return $due->count();
    }
}
