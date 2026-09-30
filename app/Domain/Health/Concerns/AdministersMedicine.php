<?php

namespace App\Domain\Health\Concerns;

use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\MedicineBatch;
use App\Domain\Health\Models\Treatment;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Health\Models\WithdrawalPeriod;
use App\Domain\System\Exceptions\DomainException;
use Carbon\CarbonInterface;

/** Shared checks for giving a medicine or vaccine, and the withdrawal period that follows. */
trait AdministersMedicine
{
    protected function assertAdministrable(Animal $animal, Medicine $medicine, ?int $batchId, CarbonInterface $on): ?MedicineBatch
    {
        $animal->refresh();

        if (! $animal->isActive()) {
            throw new DomainException("{$animal->animal_number} is {$animal->status->label()}; medicines can no longer be recorded.", 'animal_not_active');
        }

        if (! $medicine->is_active) {
            throw new DomainException("{$medicine->name} is inactive.", 'medicine_inactive');
        }

        if ($on->gt(now()->addMinutes(5)) || ($animal->birth_date && $on->lt($animal->birth_date))) {
            throw new DomainException('The date must be between the animal\'s birth and today.', 'administered_date');
        }

        return $batchId ? $this->usableBatch($medicine, $batchId, $on) : null;
    }

    private function usableBatch(Medicine $medicine, int $batchId, CarbonInterface $on): MedicineBatch
    {
        $batch = MedicineBatch::where('medicine_id', $medicine->id)->find($batchId)
            ?? throw new DomainException("That batch is not a batch of {$medicine->name}.", 'batch_mismatch');

        if (! $batch->is_active) {
            throw new DomainException("Batch {$batch->batch_number} is not in use.", 'batch_inactive');
        }

        if ($batch->isExpiredOn($on)) {
            throw new DomainException("Batch {$batch->batch_number} expired on {$batch->expiry_date->format('d M Y')}.", 'batch_expired');
        }

        return $batch;
    }

    /** Vets may lengthen a withdrawal but never shorten the medicine's own. */
    protected function effectiveWithdrawalDays(Medicine $medicine, ?int $override): int
    {
        if ($override !== null && $override < $medicine->default_withdrawal_days) {
            throw new DomainException("The withdrawal period for {$medicine->name} cannot be shorter than {$medicine->default_withdrawal_days} days.", 'withdrawal_shortened');
        }

        return $override ?? $medicine->default_withdrawal_days;
    }

    protected function openWithdrawal(Animal $animal, Medicine $medicine, int $days, CarbonInterface $on, Treatment|Vaccination $source): ?WithdrawalPeriod
    {
        if ($days < 1) {
            return null;
        }

        return WithdrawalPeriod::create([
            'animal_id' => $animal->id,
            'medicine_id' => $medicine->id,
            'treatment_id' => $source instanceof Treatment ? $source->id : null,
            'vaccination_id' => $source instanceof Vaccination ? $source->id : null,
            'starts_on' => $on->copy()->startOfDay(),
            'ends_on' => $on->copy()->startOfDay()->addDays($days),
        ]);
    }
}
