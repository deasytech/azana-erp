<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Models\HealthEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\HealthEventKind;
use App\Enums\HealthSeverity;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class ReportHealthEvent
{
    public function __invoke(
        Animal $animal,
        HealthEventKind $kind,
        HealthSeverity $severity,
        CarbonInterface $observedOn,
        ?string $symptoms = null,
        ?int $diseaseId = null,
        ?int $visitId = null,
        ?User $actor = null,
    ): HealthEvent {
        $animal->refresh();

        if (! $animal->isActive()) {
            throw new DomainException("{$animal->animal_number} is {$animal->status->label()}; health cases can no longer be opened.", 'animal_not_active');
        }

        if ($observedOn->gt(now()->addMinutes(5)) || ($animal->birth_date && $observedOn->lt($animal->birth_date))) {
            throw new DomainException('The date must be between the animal\'s birth and today.', 'observed_date');
        }

        return $animal->healthEvents()->create([
            'kind' => $kind,
            'severity' => $severity,
            'observed_on' => $observedOn,
            'symptoms' => $symptoms,
            'disease_id' => $diseaseId,
            'veterinary_visit_id' => $visitId,
            'user_id' => ($actor ?? Auth::user())?->getKey(),
        ]);
    }
}
