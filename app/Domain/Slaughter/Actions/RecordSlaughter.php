<?php

namespace App\Domain\Slaughter\Actions;

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Production\Actions\RemovePigsFromBatch;
use App\Domain\Slaughter\Events\SlaughterCompleted;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\Slaughter\Models\SlaughterRecord;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\BatchEventType;
use App\Enums\CarcassStatus;
use App\Enums\PostMortemResult;
use App\Enums\SlaughterRecordStatus;
use App\Models\User;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records the slaughter of a received pig: the hot carcass weight, the post-mortem inspection and any weight condemned.
 * The carcass gets a number and its dressing percentage (carcass weight / live weight x 100); the animal is marked
 * slaughtered (the withdrawal guard applies again) or the pigs come off their batch. A fully condemned carcass is a loss
 * and makes no meat.
 */
class RecordSlaughter
{
    public function __construct(
        private readonly NextNumber $nextNumber,
        private readonly ChangeAnimalStatus $changeStatus,
        private readonly RemovePigsFromBatch $removePigs,
    ) {}

    public function __invoke(SlaughterRecord $record, string $hotWeightKg, PostMortemResult $postMortem, ?string $condemnedKg = null, ?string $notes = null, ?CarbonInterface $slaughteredAt = null, ?User $actor = null): Carcass
    {
        $slaughteredAt ??= now();

        return DB::transaction(function () use ($record, $hotWeightKg, $postMortem, $condemnedKg, $notes, $slaughteredAt, $actor) {
            $record = SlaughterRecord::lockForUpdate()->with(['batch', 'animal', 'productionBatch'])->findOrFail($record->id);

            $this->validate($record, $hotWeightKg, $postMortem, $condemned = $this->condemned($postMortem, $hotWeightKg, $condemnedKg), $slaughteredAt);

            $carcass = Carcass::create([
                'number' => sprintf('CAR-%06d', ($this->nextNumber)('carcass')),
                'slaughter_record_id' => $record->id,
                'slaughtered_at' => $slaughteredAt,
                'live_weight_kg' => $record->live_weight_kg,
                'hot_weight_kg' => $hotWeightKg,
                'dressing_percent' => Ratio::percent($hotWeightKg, (string) $record->live_weight_kg, 2),
                'post_mortem' => $postMortem,
                'condemned_kg' => $condemned,
                'post_mortem_notes' => $notes,
                'status' => $postMortem === PostMortemResult::Condemned ? CarcassStatus::Condemned : CarcassStatus::Hanging,
                'slaughtered_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            $this->removeLive($record, $slaughteredAt, $actor);
            $record->update(['status' => $postMortem === PostMortemResult::Condemned ? SlaughterRecordStatus::Condemned : SlaughterRecordStatus::Slaughtered]);
            SlaughterCompleted::dispatch($carcass);

            return $carcass;
        });
    }

    private function condemned(PostMortemResult $result, string $hot, ?string $given): string
    {
        return match ($result) {
            PostMortemResult::Passed => '0',
            PostMortemResult::Condemned => $hot,
            PostMortemResult::Partial => (string) $given,
        };
    }

    private function validate(SlaughterRecord $record, string $hot, PostMortemResult $result, string $condemned, CarbonInterface $at): void
    {
        $record->status === SlaughterRecordStatus::Received || throw new DomainException("This pig is {$record->status->label()}: only a pig that was received can be slaughtered.", 'record_state');
        $record->batch->isOpen() || throw new DomainException("{$record->batch->number} is {$record->batch->status->label()}.", 'batch_state');

        if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $hot) || bccomp($hot, '0', 2) <= 0 || bccomp($hot, (string) $record->live_weight_kg, 2) > 0) {
            throw new DomainException("The carcass weight must be above zero and no more than the live weight of {$record->live_weight_kg} kg.", 'hot_weight');
        }

        if ($result === PostMortemResult::Partial && (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $condemned) || bccomp($condemned, '0', 2) <= 0 || bccomp($condemned, $hot, 2) >= 0)) {
            throw new DomainException('Give the weight condemned: above zero and less than the carcass weight.', 'condemned_weight');
        }

        if ($at->gt(now()->addMinutes(5)) || $at->lt($record->received_at)) {
            throw new DomainException('The slaughter cannot be in the future or before the pig was received.', 'slaughter_date');
        }
    }

    private function removeLive(SlaughterRecord $record, CarbonInterface $at, ?User $actor): void
    {
        $reason = "Slaughtered on {$record->batch->number}";

        $record->animal
            ? ($this->changeStatus)($record->animal, AnimalStatus::Slaughtered, $reason, $at, $actor)
            : ($this->removePigs)($record->productionBatch, BatchEventType::Slaughter, $record->heads, $at, $reason, $actor, "slaughter:{$record->id}");
    }
}
