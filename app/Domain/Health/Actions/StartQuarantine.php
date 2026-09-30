<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Actions\RecordAnimalMovement;
use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Models\QuarantineRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\QuarantineType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Puts an animal into quarantine or isolation, optionally moving it to a pen/location in the same step. */
class StartQuarantine
{
    public function __construct(private readonly RecordAnimalMovement $moveAnimal) {}

    public function __invoke(
        Animal $animal,
        QuarantineType $type,
        CarbonInterface $startedOn,
        string $reason,
        ?int $penId = null,
        ?int $locationId = null,
        ?int $healthEventId = null,
        ?User $actor = null,
    ): QuarantineRecord {
        return DB::transaction(function () use ($animal, $type, $startedOn, $reason, $penId, $locationId, $healthEventId, $actor) {
            $animal = Animal::lockForUpdate()->findOrFail($animal->id);

            if (! $animal->isActive()) {
                throw new DomainException("{$animal->animal_number} is {$animal->status->label()} and cannot be quarantined.", 'animal_not_active');
            }

            if (trim($reason) === '') {
                throw new DomainException('A reason is required.', 'reason_required');
            }

            if ($startedOn->gt(now()->addMinutes(5)) || ($animal->birth_date && $startedOn->lt($animal->birth_date))) {
                throw new DomainException('The date must be between the animal\'s birth and today.', 'quarantine_date');
            }

            if (QuarantineRecord::where('animal_id', $animal->id)->whereNull('released_on')->exists()) {
                throw new DomainException("{$animal->animal_number} is already in quarantine or isolation.", 'already_quarantined');
            }

            if ($penId || $locationId) {
                ($this->moveAnimal)($animal, $penId, $locationId, null, null, ucfirst($type->value).': '.trim($reason), $actor);
            }

            return QuarantineRecord::create([
                'animal_id' => $animal->id,
                'type' => $type,
                'started_on' => $startedOn,
                'reason' => trim($reason),
                'health_event_id' => $healthEventId,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);
        });
    }
}
