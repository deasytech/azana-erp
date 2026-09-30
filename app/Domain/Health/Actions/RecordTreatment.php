<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Concerns\ReplaysIdempotentRequests;
use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Concerns\AdministersMedicine;
use App\Domain\Health\Models\HealthEvent;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\Treatment;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Records a treatment and, if the medicine has a withdrawal period, starts it automatically. */
class RecordTreatment
{
    use AdministersMedicine, ReplaysIdempotentRequests;

    /** @param array{dose?: ?string, dose_unit?: ?string, route?: ?string, notes?: ?string, health_event_id?: ?int, veterinary_visit_id?: ?int, batch_id?: ?int, withdrawal_days?: ?int, idempotency_key?: ?string} $options */
    public function __invoke(Animal $animal, Medicine $medicine, CarbonInterface $administeredOn, array $options = [], ?User $actor = null): Treatment
    {
        return DB::transaction(function () use ($animal, $medicine, $administeredOn, $options, $actor) {
            if ($existing = $this->replay(Treatment::class, $animal, $options['idempotency_key'] ?? null)) {
                return $existing;
            }

            $batch = $this->assertAdministrable($animal, $medicine, $options['batch_id'] ?? null, $administeredOn);
            $this->assertEvent($animal, $options['health_event_id'] ?? null);
            $days = $this->effectiveWithdrawalDays($medicine, $options['withdrawal_days'] ?? null);

            $treatment = Treatment::create([
                'animal_id' => $animal->id,
                'medicine_id' => $medicine->id,
                'medicine_batch_id' => $batch?->id,
                'health_event_id' => $options['health_event_id'] ?? null,
                'veterinary_visit_id' => $options['veterinary_visit_id'] ?? null,
                'dose' => $options['dose'] ?? null,
                'dose_unit' => $options['dose_unit'] ?? null,
                'route' => $options['route'] ?? null,
                'administered_on' => $administeredOn,
                'withdrawal_days' => $days,
                'notes' => $options['notes'] ?? null,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $options['idempotency_key'] ?? null,
            ]);

            $this->openWithdrawal($animal, $medicine, $days, $administeredOn, $treatment);

            return $treatment;
        });
    }

    private function assertEvent(Animal $animal, ?int $eventId): void
    {
        if ($eventId && ! HealthEvent::where('animal_id', $animal->id)->whereKey($eventId)->exists()) {
            throw new DomainException('That health case does not belong to this animal.', 'event_mismatch');
        }
    }
}
