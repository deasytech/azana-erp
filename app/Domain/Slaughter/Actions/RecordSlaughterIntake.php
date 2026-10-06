<?php

namespace App\Domain\Slaughter\Actions;

use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Health\Actions\AssertAnimalCanEnterFoodChain;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Sales\Models\StockReservation;
use App\Domain\Slaughter\Models\SlaughterBatch;
use App\Domain\Slaughter\Models\SlaughterRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnteMortemResult;
use App\Enums\ReservationStatus;
use App\Enums\SlaughterBatchStatus;
use App\Enums\SlaughterRecordStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Receives a pig - or a group of pigs from a batch - at the slaughterhouse with its live weight and ante-mortem
 * inspection. A pig that fails inspection is rejected and goes no further. Withdrawal periods and quarantine are
 * checked here, and a pig reserved for a customer cannot be received. The cost of raising it is taken from the farm's
 * records unless given.
 */
class RecordSlaughterIntake
{
    public function __construct(
        private readonly AssertAnimalCanEnterFoodChain $foodChain,
        private readonly RecordWeight $recordWeight,
        private readonly GetLiveCost $liveCost,
        private readonly ResolveSettings $settings,
    ) {}

    public function __invoke(SlaughterBatch $day, Animal|ProductionBatch $source, string $liveWeightKg, AnteMortemResult $anteMortem, ?string $notes = null, int $heads = 1, ?int $liveCostMinor = null, ?CarbonInterface $receivedAt = null, ?User $actor = null): SlaughterRecord
    {
        $receivedAt ??= now();

        return DB::transaction(function () use ($day, $source, $liveWeightKg, $anteMortem, $notes, $heads, $liveCostMinor, $receivedAt, $actor) {
            $day = SlaughterBatch::lockForUpdate()->findOrFail($day->id);
            $day->isOpen() || throw new DomainException("{$day->number} is {$day->status->label()} and takes no more pigs.", 'batch_state');
            $this->validate($liveWeightKg, $anteMortem, $notes, $receivedAt, $source instanceof Animal ? 1 : $heads);

            $animal = $source instanceof Animal ? Animal::lockForUpdate()->with('category')->findOrFail($source->id) : null;
            $batch = $source instanceof ProductionBatch ? ProductionBatch::lockForUpdate()->findOrFail($source->id) : null;

            $animal ? $this->checkAnimal($animal, $receivedAt) : $this->checkBatch($batch, $heads);
            $animal && ($this->recordWeight)($animal, $liveWeightKg, $receivedAt, 'scale', 'Live weight at slaughter intake', $actor);

            $record = SlaughterRecord::create([
                'slaughter_batch_id' => $day->id,
                'animal_id' => $animal?->id,
                'production_batch_id' => $batch?->id,
                'heads' => $animal ? 1 : $heads,
                'status' => $anteMortem === AnteMortemResult::Passed ? SlaughterRecordStatus::Received : SlaughterRecordStatus::Rejected,
                'received_at' => $receivedAt,
                'live_weight_kg' => $liveWeightKg,
                'live_cost_minor' => $liveCostMinor ?? ($animal ? $this->liveCost->forAnimal($animal) : $this->liveCost->forBatch($batch, $heads)),
                'ante_mortem' => $anteMortem,
                'ante_mortem_notes' => $notes,
                'received_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            $day->status === SlaughterBatchStatus::Scheduled && $day->update(['status' => SlaughterBatchStatus::InProgress]);

            return $record;
        });
    }

    private function validate(string $weight, AnteMortemResult $result, ?string $notes, CarbonInterface $at, int $heads): void
    {
        $max = bcmul((string) $this->settings->get('animals.max_weight_kg'), (string) $heads, 2);

        if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $weight) || bccomp($weight, '0', 2) <= 0 || bccomp($weight, $max, 2) > 0) {
            throw new DomainException("The live weight must be a positive number of kg (at most {$max} kg for {$heads} pig(s)).", 'live_weight');
        }

        if ($result === AnteMortemResult::Failed && trim((string) $notes) === '') {
            throw new DomainException('Say why the pig failed inspection.', 'inspection_notes');
        }

        if ($at->gt(now()->addMinutes(5))) {
            throw new DomainException('A pig cannot be received in the future.', 'intake_future');
        }
    }

    private function checkAnimal(Animal $animal, CarbonInterface $at): void
    {
        $animal->isActive() || throw new DomainException("{$animal->animal_number} is {$animal->status->label()}.", 'animal_not_active');
        ($this->foodChain)($animal, $at);

        if (SlaughterRecord::where('animal_id', $animal->id)->where('status', SlaughterRecordStatus::Received)->exists()) {
            throw new DomainException("{$animal->animal_number} has already been received for slaughter.", 'already_received');
        }

        if (StockReservation::where('animal_id', $animal->id)->where('status', ReservationStatus::Active)->exists()) {
            throw new DomainException("{$animal->animal_number} is reserved for a customer's order.", 'animal_reserved');
        }
    }

    private function checkBatch(ProductionBatch $batch, int $heads): void
    {
        $batch->isActive() || throw new DomainException("{$batch->code} is closed.", 'batch_closed');

        if ($heads < 1) {
            throw new DomainException('Say how many pigs were received.', 'heads');
        }

        $reserved = (int) StockReservation::where('production_batch_id', $batch->id)->where('status', ReservationStatus::Active)->sum('quantity');
        $waiting = (int) SlaughterRecord::where('production_batch_id', $batch->id)->where('status', SlaughterRecordStatus::Received)->sum('heads');
        $free = $batch->headCount() - $reserved - $waiting;

        if ($free < $heads) {
            throw new DomainException("{$batch->code}: only {$free} pigs are free ({$reserved} reserved for customers, {$waiting} already waiting for slaughter), {$heads} received.", 'insufficient_pigs');
        }
    }
}
