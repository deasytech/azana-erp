<?php

namespace App\Domain\Production\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchAnimal;
use App\Enums\AnimalStatus;
use App\Enums\BatchEventType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Takes an animal out of its batch when it leaves the farm (called by ChangeAnimalStatus), so deaths,
 * sales and culls reduce the batch count. Returns null when the animal is not in a batch.
 */
class RemoveAnimalFromBatch
{
    public function __construct(private readonly PostBatchEvent $postEvent) {}

    public function __invoke(Animal $animal, AnimalStatus|string $reason, CarbonInterface $on): ?ProductionBatchAnimal
    {
        $membership = ProductionBatchAnimal::where('animal_id', $animal->id)->whereNull('left_on')->first();

        if (! $membership) {
            return null;
        }

        $type = $reason instanceof AnimalStatus ? $this->eventFor($reason) : BatchEventType::TransferOut;
        $code = $reason instanceof AnimalStatus ? $reason->value : $reason;

        return DB::transaction(function () use ($membership, $animal, $type, $code, $on) {
            $batch = ProductionBatch::findOrFail($membership->production_batch_id);
            // Membership started on or before the exit; never record the exit before the animal joined.
            $on = $on->lt($membership->joined_on) ? $membership->joined_on->copy() : $on;

            ($this->postEvent)($batch, $type, -1, $on, ['animal_id' => $animal->id, 'notes' => "Animal {$animal->animal_number}: {$code}"]);
            $membership->forceFill(['left_on' => $on, 'left_reason' => $code])->save();

            return $membership;
        });
    }

    private function eventFor(AnimalStatus $status): BatchEventType
    {
        return match ($status) {
            AnimalStatus::Dead => BatchEventType::Mortality,
            AnimalStatus::Culled => BatchEventType::Cull,
            AnimalStatus::Sold => BatchEventType::Sale,
            AnimalStatus::Slaughtered => BatchEventType::Slaughter,
            default => BatchEventType::TransferOut,
        };
    }
}
