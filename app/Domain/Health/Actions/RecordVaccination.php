<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Concerns\ReplaysIdempotentRequests;
use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Concerns\AdministersMedicine;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Health\Models\VaccinationSchedule;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a vaccination. With a schedule, the vaccine comes from the schedule and the animal must be in
 * the schedule's category; without one it is an ad-hoc vaccination with any vaccine.
 */
class RecordVaccination
{
    use AdministersMedicine, ReplaysIdempotentRequests;

    /** @param array{schedule_id?: ?int, medicine_id?: ?int, batch_id?: ?int, dose?: ?string, notes?: ?string, withdrawal_days?: ?int, idempotency_key?: ?string} $options */
    public function __invoke(Animal $animal, CarbonInterface $administeredOn, array $options = [], ?User $actor = null): Vaccination
    {
        return DB::transaction(function () use ($animal, $administeredOn, $options, $actor) {
            if ($existing = $this->replay(Vaccination::class, $animal, $options['idempotency_key'] ?? null)) {
                return $existing;
            }

            $schedule = ! empty($options['schedule_id']) ? VaccinationSchedule::with('medicine')->findOrFail($options['schedule_id']) : null;
            $medicine = $this->vaccine($schedule, $options['medicine_id'] ?? null);
            $batch = $this->assertAdministrable($animal, $medicine, $options['batch_id'] ?? null, $administeredOn);
            $schedule && $this->assertScheduleFits($animal, $schedule, $administeredOn);
            $days = $this->effectiveWithdrawalDays($medicine, $options['withdrawal_days'] ?? null);

            $vaccination = Vaccination::create([
                'animal_id' => $animal->id,
                'vaccination_schedule_id' => $schedule?->id,
                'medicine_id' => $medicine->id,
                'medicine_batch_id' => $batch?->id,
                'administered_on' => $administeredOn,
                'dose' => $options['dose'] ?? null,
                'withdrawal_days' => $days,
                'notes' => $options['notes'] ?? null,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $options['idempotency_key'] ?? null,
            ]);

            $this->openWithdrawal($animal, $medicine, $days, $administeredOn, $vaccination);

            return $vaccination;
        });
    }

    private function vaccine(?VaccinationSchedule $schedule, ?int $medicineId): Medicine
    {
        if ($medicineId && $schedule && $medicineId !== $schedule->medicine_id) {
            throw new DomainException('That vaccine is not the one in the vaccination schedule.', 'schedule_mismatch');
        }

        $medicine = $schedule?->medicine ?? Medicine::find($medicineId)
            ?? throw new DomainException('Choose the vaccine that was given.', 'vaccine_required');

        $medicine->loadMissing('type');

        if (! $medicine->isVaccine()) {
            throw new DomainException("{$medicine->name} is not a vaccine.", 'not_a_vaccine');
        }

        return $medicine;
    }

    private function assertScheduleFits(Animal $animal, VaccinationSchedule $schedule, CarbonInterface $on): void
    {
        if (! $schedule->is_active) {
            throw new DomainException("The {$schedule->name} schedule is inactive.", 'schedule_inactive');
        }

        if ($schedule->category_id && $schedule->category_id !== $animal->category_id) {
            throw new DomainException("The {$schedule->name} schedule does not apply to this category of animal.", 'schedule_category');
        }

        if (Vaccination::where('animal_id', $animal->id)->where('vaccination_schedule_id', $schedule->id)->whereDate('administered_on', $on)->exists()) {
            throw new DomainException('This vaccination was already recorded for that day.', 'vaccination_duplicate');
        }
    }
}
