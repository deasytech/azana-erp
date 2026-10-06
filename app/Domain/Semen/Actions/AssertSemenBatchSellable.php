<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Semen\Models\SemenBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\SemenBatchStatus;

/**
 * The one rule for semen leaving the farm or being used: only a released batch that has not expired and is not
 * blocked. Sales (Phase 11) and artificial insemination both go through it.
 */
class AssertSemenBatchSellable
{
    public function __invoke(SemenBatch|int $batch): SemenBatch
    {
        $batch = SemenBatch::with('inventoryBatch')->findOrFail($batch instanceof SemenBatch ? $batch->id : $batch);

        if ($batch->status !== SemenBatchStatus::Released) {
            throw new DomainException("{$batch->number} is {$batch->status->label()} and cannot be sold or used.", 'semen_not_released');
        }

        if ($batch->isExpired()) {
            throw new DomainException("{$batch->number} expired on {$batch->expiry_date->format('d M Y')}.", 'semen_expired');
        }

        if ($batch->inventoryBatch?->is_active !== true) {
            throw new DomainException("{$batch->number} is blocked.", 'semen_blocked');
        }

        return $batch;
    }
}
