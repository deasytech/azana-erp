<?php

namespace App\Domain\Breeding\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Models\HeatEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class RecordHeat
{
    public function __invoke(Animal $sow, CarbonInterface $detectedOn, ?string $notes = null, ?User $actor = null): HeatEvent
    {
        $sow->refresh();

        if (! $sow->isBreedingFemale() || ! $sow->isActive()) {
            throw new DomainException('Heat can only be recorded for an active sow or gilt.', 'not_breeding_female');
        }

        if ($detectedOn->isFuture()) {
            throw new DomainException('Heat cannot be dated in the future.', 'heat_future');
        }

        if ($sow->birth_date && $detectedOn->lt($sow->birth_date)) {
            throw new DomainException('Heat cannot be dated before the animal was born.', 'heat_before_birth');
        }

        if (HeatEvent::where('sow_id', $sow->id)->whereDate('detected_on', $detectedOn)->exists()) {
            throw new DomainException("Heat was already recorded for {$sow->animal_number} on that day.", 'heat_duplicate');
        }

        return HeatEvent::create([
            'sow_id' => $sow->id,
            'detected_on' => $detectedOn,
            'notes' => $notes,
            'user_id' => ($actor ?? Auth::user())?->getKey(),
        ]);
    }
}
