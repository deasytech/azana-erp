<?php

namespace App\Domain\Breeding\Concerns;

use App\Domain\Breeding\Models\BreedingService;
use App\Enums\ServiceOutcome;
use Illuminate\Database\Eloquent\Collection;

/** Needs a `$settings` property (ResolveSettings). */
trait FindsSameHeatServices
{
    /**
     * The sow's open services close enough in time to count as one mating (double mating).
     *
     * @return Collection<int, BreedingService>
     */
    protected function sameHeatServices(BreedingService $service): Collection
    {
        $window = (int) $this->settings->get('breeding.same_heat_window_days');

        return BreedingService::where('sow_id', $service->sow_id)
            ->whereIn('outcome', [ServiceOutcome::Pending->value, ServiceOutcome::Pregnant->value])
            ->whereBetween('serviced_on', [$service->serviced_on->copy()->subDays($window), $service->serviced_on->copy()->addDays($window)])
            ->lockForUpdate()
            ->get();
    }
}
