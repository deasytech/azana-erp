<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Concerns\DatesExitEvents;
use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Health\Models\CullingRecord;
use App\Domain\Litter\Actions\GetSowPerformance;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\CullHealthStatus;
use App\Enums\DisposalType;
use App\Enums\LookupCategory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Culls an animal. Keeps everything management needs later: reason, weight, health status, a snapshot
 * of production performance and the disposal (sale) value. Animals that may enter the food chain
 * (sold / slaughtered) must be clear of withdrawal periods and quarantine.
 */
class RecordCulling
{
    use DatesExitEvents;

    public function __construct(
        private readonly ChangeAnimalStatus $changeStatus,
        private readonly RecordWeight $recordWeight,
        private readonly AssertAnimalCanEnterFoodChain $foodChainGuard,
        private readonly GetSowPerformance $sowPerformance,
    ) {}

    public function __invoke(
        Animal $animal,
        CarbonInterface $culledOn,
        int $reasonId,
        string $weightKg,
        CullHealthStatus $healthStatus,
        DisposalType $disposal,
        int $disposalValueMinor,
        ?string $notes = null,
        ?User $actor = null,
    ): CullingRecord {
        return DB::transaction(function () use ($animal, $culledOn, $reasonId, $weightKg, $healthStatus, $disposal, $disposalValueMinor, $notes, $actor) {
            $animal = Animal::lockForUpdate()->with('category')->findOrFail($animal->id);
            $reason = LookupValue::where('category', LookupCategory::CullReason->value)->where('is_active', true)->find($reasonId)
                ?? throw new DomainException('Choose a valid culling reason.', 'invalid_reason');

            $this->validate($animal, $culledOn, $disposal, $disposalValueMinor);
            $at = $this->exitMoment($culledOn);
            ($this->recordWeight)($animal, $weightKg, $at, 'scale', 'Weight at culling', $actor);

            $record = CullingRecord::create([
                'animal_id' => $animal->id,
                'culled_on' => $culledOn,
                'reason_id' => $reason->id,
                'weight_kg' => $weightKg,
                'health_status' => $healthStatus,
                'performance' => $this->performance($animal, $weightKg, $culledOn),
                'disposal' => $disposal,
                'disposal_value_minor' => $disposalValueMinor,
                'notes' => $notes,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);

            ($this->changeStatus)($animal, AnimalStatus::Culled, "Culled: {$reason->name}", $at, $actor);

            return $record;
        });
    }

    private function validate(Animal $animal, CarbonInterface $culledOn, DisposalType $disposal, int $value): void
    {
        if (! $animal->isActive()) {
            throw new DomainException("{$animal->animal_number} is already {$animal->status->label()}.", 'animal_not_active');
        }

        if ($culledOn->gt(now()->addMinutes(5)) || ($animal->birth_date && $culledOn->lt($animal->birth_date))) {
            throw new DomainException('The culling date must be between the animal\'s birth and today.', 'culling_date');
        }

        if ($value < 0) {
            throw new DomainException('The disposal value cannot be negative (use 0 when there is none).', 'disposal_value');
        }

        $disposal->entersFoodChain() && ($this->foodChainGuard)($animal, $culledOn);
    }

    /** @return array<string, int|string|null> */
    private function performance(Animal $animal, string $weightKg, CarbonInterface $culledOn): array
    {
        $base = [
            'category' => $animal->category->name,
            'age_days' => $animal->birth_date ? (int) $animal->birth_date->diffInDays($culledOn) : null,
            'weight_kg' => $weightKg,
        ];

        return $animal->isBreedingFemale() ? $base + $this->sowPerformance->__invoke($animal) : $base;
    }
}
