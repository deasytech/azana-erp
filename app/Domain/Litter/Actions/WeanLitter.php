<?php

namespace App\Domain\Litter\Actions;

use App\Domain\Animal\Actions\RecordAnimalMovement;
use App\Domain\Breeding\Events\WeaningRecorded;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Litter\Models\Litter;
use App\Domain\Litter\Models\WeaningRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\LitterStatus;
use App\Enums\LookupCategory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Weans a litter: closes it, records the count and total weight, moves its tracked piglets on to
 * the weaner category (and optionally into a pen), and dates the sow's next service.
 */
class WeanLitter
{
    public function __construct(
        private readonly ResolveSettings $settings,
        private readonly RecordAnimalMovement $moveAnimal,
    ) {}

    public function __invoke(
        Litter $litter,
        CarbonInterface $weanedOn,
        int $weanedCount,
        ?string $totalWeightKg = null,
        ?int $destinationPenId = null,
        ?string $notes = null,
        ?User $actor = null,
    ): WeaningRecord {
        return DB::transaction(function () use ($litter, $weanedOn, $weanedCount, $totalWeightKg, $destinationPenId, $notes, $actor) {
            $litter = Litter::lockForUpdate()->with('farrowing')->findOrFail($litter->id);
            $this->validate($litter, $weanedOn, $weanedCount, $totalWeightKg);

            $record = WeaningRecord::create([
                'litter_id' => $litter->id,
                'weaned_on' => $weanedOn,
                'weaned_count' => $weanedCount,
                'total_weight_kg' => $totalWeightKg,
                'expected_next_service_on' => $weanedOn->copy()->addDays((int) $this->settings->get('breeding.wean_to_service_days')),
                'notes' => $notes,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);

            $litter->forceFill(['status' => LitterStatus::Weaned, 'weaned_on' => $weanedOn])->save();
            $this->updateTrackedPiglets($litter, $weanedOn, $destinationPenId, $actor);

            WeaningRecorded::dispatch($record);

            return $record;
        });
    }

    private function validate(Litter $litter, CarbonInterface $weanedOn, int $weanedCount, ?string $totalWeightKg): void
    {
        if (! $litter->isSuckling()) {
            throw new DomainException("{$litter->litter_number} is already weaned.", 'litter_weaned');
        }

        if ($weanedOn->isFuture() || $weanedOn->lt($litter->born_on)) {
            throw new DomainException('The weaning date must be between the litter\'s birth and today.', 'weaning_date');
        }

        $remaining = $litter->farrowing->born_alive - $litter->losses()->sum('count');

        if ($weanedCount < 0 || $weanedCount > $remaining) {
            throw new DomainException("Weaned piglets must be between 0 and {$remaining} (born alive less losses).", 'weaned_count');
        }

        $hasWeight = $totalWeightKg !== null;

        if ($hasWeight && ($weanedCount === 0 || ! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $totalWeightKg) || bccomp($totalWeightKg, '0', 2) <= 0)) {
            throw new DomainException('The total weaning weight must be a positive number (and needs weaned piglets).', 'weaning_weight');
        }
    }

    private function updateTrackedPiglets(Litter $litter, CarbonInterface $weanedOn, ?int $penId, ?User $actor): void
    {
        $weaner = LookupValue::where('category', LookupCategory::AnimalCategory->value)->where('code', 'weaner')->where('is_active', true)->value('id');

        foreach ($litter->piglets()->with('animal')->get() as $piglet) {
            if (! $piglet->animal->isActive()) {
                continue;
            }

            $weaner && $piglet->animal->update(['category_id' => $weaner]);
            $penId && ($this->moveAnimal)($piglet->animal, $penId, null, $weanedOn, null, 'Weaning', $actor);
        }
    }
}
