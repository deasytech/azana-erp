<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Animal\Concerns\DatesExitEvents;
use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Health\Events\MortalityRecorded;
use App\Domain\Health\Models\Disease;
use App\Domain\Health\Models\MortalityRecord;
use App\Domain\Litter\Models\Piglet;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\LookupCategory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a death. Captures the pen, litter, sow, breed, age, production stage and last weight as
 * they were at death, so mortality can be analysed on any of those later. A tracked piglet of a suckling
 * litter also gets its pre-weaning loss entry, unless the caller (RecordLitterLoss) records that itself.
 */
class RecordMortality
{
    use DatesExitEvents;

    public function __construct(private readonly ChangeAnimalStatus $changeStatus) {}

    public function __invoke(
        Animal $animal,
        CarbonInterface $diedOn,
        int $causeId,
        ?int $diseaseId = null,
        ?string $notes = null,
        ?User $actor = null,
        bool $recordLitterLoss = true,
    ): MortalityRecord {
        return DB::transaction(function () use ($animal, $diedOn, $causeId, $diseaseId, $notes, $actor, $recordLitterLoss) {
            $animal = Animal::lockForUpdate()->with(['parentage', 'category'])->findOrFail($animal->id);
            $cause = $this->cause($causeId);
            $this->validate($animal, $diedOn, $diseaseId);

            $record = MortalityRecord::create([
                'animal_id' => $animal->id,
                'died_on' => $diedOn,
                'cause_id' => $cause->id,
                'disease_id' => $diseaseId,
                'age_days' => $animal->birth_date ? (int) $animal->birth_date->diffInDays($diedOn) : null,
                'category_id' => $animal->category_id,
                'breed_id' => $animal->breed_id,
                'pen_id' => $animal->current_pen_id,
                'litter_id' => $animal->parentage?->litter_id,
                'sow_id' => $animal->parentage?->dam_id,
                'weight_kg' => $animal->latestWeight()?->weight_kg,
                'notes' => $notes,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);

            ($this->changeStatus)($animal, AnimalStatus::Dead, "Died: {$cause->name}", $this->exitMoment($diedOn), $actor);
            $recordLitterLoss && $this->recordLitterLoss($animal, $diedOn, $cause, $actor);

            MortalityRecorded::dispatch($record);

            return $record;
        });
    }

    private function cause(int $causeId): LookupValue
    {
        return LookupValue::where('category', LookupCategory::MortalityCause->value)->where('is_active', true)->find($causeId)
            ?? throw new DomainException('Choose a valid cause of death.', 'invalid_cause');
    }

    private function validate(Animal $animal, CarbonInterface $diedOn, ?int $diseaseId): void
    {
        if (! $animal->isActive()) {
            throw new DomainException("{$animal->animal_number} is already {$animal->status->label()}.", 'animal_not_active');
        }

        if ($diedOn->gt(now()->addMinutes(5)) || ($animal->birth_date && $diedOn->lt($animal->birth_date))) {
            throw new DomainException('The date of death must be between the animal\'s birth and today.', 'death_date');
        }

        if ($diseaseId && ! Disease::where('is_active', true)->whereKey($diseaseId)->exists()) {
            throw new DomainException('Choose a valid disease.', 'invalid_disease');
        }
    }

    private function recordLitterLoss(Animal $animal, CarbonInterface $diedOn, LookupValue $cause, ?User $actor): void
    {
        $piglet = Piglet::with('litter')->where('animal_id', $animal->id)->first();

        if ($piglet?->litter->isSuckling()) {
            $piglet->litter->losses()->create([
                'occurred_on' => $diedOn, 'count' => 1, 'cause' => $cause->name, 'animal_id' => $animal->id,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);
        }
    }
}
