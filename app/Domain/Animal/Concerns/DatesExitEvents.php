<?php

namespace App\Domain\Animal\Concerns;

use Carbon\CarbonInterface;

/** Turns the date of a death or cull into the moment used for the animal's exit records. */
trait DatesExitEvents
{
    /** Today counts as now (so earlier movements today stay in order); past days count as end of day. */
    protected function exitMoment(CarbonInterface $date): CarbonInterface
    {
        return $date->isToday() ? now() : $date->copy()->endOfDay();
    }
}
