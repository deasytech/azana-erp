<?php

namespace App\Domain\Production\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchAnimal;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\BatchEventType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Puts an individually tracked animal into a batch (it counts as one pig in the batch). */
class AddAnimalToBatch
{
    public function __construct(private readonly PostBatchEvent $postEvent) {}

    public function __invoke(ProductionBatch $batch, Animal $animal, CarbonInterface $joinedOn, ?User $actor = null): ProductionBatchAnimal
    {
        return DB::transaction(function () use ($batch, $animal, $joinedOn, $actor) {
            $animal = Animal::lockForUpdate()->findOrFail($animal->id);

            if (! $animal->isActive()) {
                throw new DomainException("{$animal->animal_number} is {$animal->status->label()} and cannot join a batch.", 'animal_not_active');
            }

            if (ProductionBatchAnimal::where('animal_id', $animal->id)->whereNull('left_on')->exists()) {
                throw new DomainException("{$animal->animal_number} already belongs to a batch.", 'already_in_batch');
            }

            ($this->postEvent)($batch, BatchEventType::Placement, 1, $joinedOn, ['animal_id' => $animal->id, 'notes' => "Animal {$animal->animal_number} joined"], $actor);

            return ProductionBatchAnimal::create(['production_batch_id' => $batch->id, 'animal_id' => $animal->id, 'joined_on' => $joinedOn]);
        });
    }
}
