<?php

namespace App\Domain\Breeding\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Litter\Models\Litter;
use App\Enums\LitterStatus;
use App\Enums\ReproductiveStatus;
use App\Enums\ServiceOutcome;

/** Derives a breeding female's reproductive status from her latest events (nothing is cached). */
class GetSowStatus
{
    public function __invoke(Animal $sow): ?ReproductiveStatus
    {
        if (! $sow->isBreedingFemale()) {
            return null;
        }

        if (Litter::where('sow_id', $sow->id)->where('status', LitterStatus::Suckling->value)->exists()) {
            return ReproductiveStatus::Lactating;
        }

        $open = BreedingService::where('sow_id', $sow->id)
            ->whereIn('outcome', [ServiceOutcome::Pending->value, ServiceOutcome::Pregnant->value])
            ->pluck('outcome');

        return match (true) {
            $open->contains(ServiceOutcome::Pregnant) => ReproductiveStatus::Pregnant,
            $open->isNotEmpty() => ReproductiveStatus::Served,
            default => ReproductiveStatus::Open,
        };
    }
}
