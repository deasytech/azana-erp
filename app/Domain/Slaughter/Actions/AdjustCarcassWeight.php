<?php

namespace App\Domain\Slaughter\Actions;

use App\Domain\Slaughter\Models\Carcass;
use App\Domain\Slaughter\Models\CarcassAdjustment;
use App\Domain\System\Actions\AssertMayDecide;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CarcassStatus;
use App\Enums\Module;
use App\Models\User;
use App\Support\Ratio;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a carcass weight that was entered wrongly, before it is made into meat. A correction is a slaughter
 * adjustment: it needs `slaughter.approve` (and, by default, someone other than who recorded it) and is kept with its reason.
 */
class AdjustCarcassWeight
{
    public function __construct(private readonly AssertMayDecide $mayDecide) {}

    public function __invoke(Carcass $carcass, string $newHotWeightKg, string $reason, User $approver): Carcass
    {
        trim($reason) !== '' || throw new DomainException('A reason is required to correct a carcass weight.', 'reason_required');

        return DB::transaction(function () use ($carcass, $newHotWeightKg, $reason, $approver) {
            $carcass = Carcass::lockForUpdate()->findOrFail($carcass->id);

            ($this->mayDecide)($approver, $carcass->slaughtered_by, Module::Slaughter, 'carcass weight correction');

            $carcass->status === CarcassStatus::Hanging || throw new DomainException("{$carcass->number} is {$carcass->status->label()}: only a carcass that has not been processed can be corrected.", 'carcass_state');

            if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $newHotWeightKg) || bccomp($newHotWeightKg, (string) $carcass->condemned_kg, 2) <= 0 || bccomp($newHotWeightKg, (string) $carcass->live_weight_kg, 2) > 0) {
                throw new DomainException("The weight must be more than what was condemned and no more than the live weight of {$carcass->live_weight_kg} kg.", 'hot_weight');
            }

            CarcassAdjustment::create([
                'carcass_id' => $carcass->id, 'old_hot_weight_kg' => $carcass->hot_weight_kg, 'new_hot_weight_kg' => $newHotWeightKg,
                'reason' => trim($reason), 'approved_by' => $approver->id,
            ]);
            $carcass->update(['hot_weight_kg' => $newHotWeightKg, 'dressing_percent' => Ratio::percent($newHotWeightKg, (string) $carcass->live_weight_kg, 2)]);

            return $carcass;
        });
    }
}
