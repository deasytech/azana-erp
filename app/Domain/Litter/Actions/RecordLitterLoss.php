<?php

namespace App\Domain\Litter\Actions;

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Litter\Models\Litter;
use App\Domain\Litter\Models\LitterLoss;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records piglets lost before weaning (append-only). When the loss is an individually tracked
 * piglet, that animal is also marked dead. Phase 06 mortality analysis builds on these records.
 */
class RecordLitterLoss
{
    public function __construct(private readonly ChangeAnimalStatus $changeStatus) {}

    public function __invoke(Litter $litter, int $count, CarbonInterface $occurredOn, ?string $cause = null, ?int $animalId = null, ?User $actor = null): LitterLoss
    {
        return DB::transaction(function () use ($litter, $count, $occurredOn, $cause, $animalId, $actor) {
            $litter = Litter::lockForUpdate()->with('farrowing')->findOrFail($litter->id);
            $this->validate($litter, $count, $occurredOn, $animalId);

            if ($animalId) {
                ($this->changeStatus)($litter->piglets()->where('animal_id', $animalId)->firstOrFail()->animal, AnimalStatus::Dead, 'Pre-weaning loss'.($cause ? ": {$cause}" : ''), $occurredOn, $actor);
            }

            return $litter->losses()->create([
                'occurred_on' => $occurredOn,
                'count' => $count,
                'cause' => $cause,
                'animal_id' => $animalId,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);
        });
    }

    private function validate(Litter $litter, int $count, CarbonInterface $occurredOn, ?int $animalId): void
    {
        if (! $litter->isSuckling()) {
            throw new DomainException("{$litter->litter_number} is already weaned; losses are pre-weaning only.", 'litter_weaned');
        }

        if ($count < 1 || ($animalId && $count !== 1)) {
            throw new DomainException('Record at least one piglet (exactly one when naming an animal).', 'loss_count');
        }

        if ($occurredOn->isFuture() || $occurredOn->lt($litter->born_on)) {
            throw new DomainException('The loss must be dated between the litter\'s birth and today.', 'loss_date');
        }

        if ($animalId && ! $litter->piglets()->where('animal_id', $animalId)->exists()) {
            throw new DomainException('That animal is not a piglet of this litter.', 'not_a_piglet');
        }

        if ($litter->losses()->sum('count') + $count > $litter->farrowing->born_alive) {
            throw new DomainException('Losses would exceed the number born alive.', 'loss_exceeds_born');
        }
    }
}
