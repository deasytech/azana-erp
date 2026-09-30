<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Events\AnimalStatusChanged;
use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalStatusHistory;
use App\Domain\Health\Actions\AssertAnimalCanEnterFoodChain;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Ends an animal's time on the farm (sold, dead, culled, slaughtered, transferred out).
 * Later phases (mortality, sales, slaughter) call this same action. Terminal statuses are final;
 * a wrongly recorded status is corrected through the approval workflow, not by editing history.
 */
class ChangeAnimalStatus
{
    public function __construct(
        private readonly RecordAnimalMovement $movements,
        private readonly AssertAnimalCanEnterFoodChain $foodChainGuard,
    ) {}

    public function __invoke(Animal $animal, AnimalStatus $to, string $reason, ?CarbonInterface $at = null, ?User $actor = null): AnimalStatusHistory
    {
        if (! $to->isTerminal()) {
            throw new DomainException('Animals can only be moved on to a final status.', 'status_invalid');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required for a status change.', 'reason_required');
        }

        return DB::transaction(function () use ($animal, $to, $reason, $at, $actor) {
            $animal = Animal::lockForUpdate()->findOrFail($animal->id);

            if ($animal->status->isTerminal()) {
                throw new DomainException("{$animal->animal_number} is already {$animal->status->label()}.", 'status_final');
            }

            $at ??= now();

            if (in_array($to, [AnimalStatus::Sold, AnimalStatus::Slaughtered], true)) {
                ($this->foodChainGuard)($animal, $at);
            }

            $actor ??= Auth::user();
            $this->movements->exit($animal, $at, "Left the farm: {$to->label()}", $actor);

            $history = $animal->statusHistory()->create([
                'from_status' => $animal->status,
                'to_status' => $to,
                'changed_at' => $at,
                'reason' => trim($reason),
                'user_id' => $actor?->getKey(),
            ]);

            $animal->forceFill(['status' => $to, 'status_changed_at' => $at])->save();

            AnimalStatusChanged::dispatch($history);

            return $history;
        });
    }
}
