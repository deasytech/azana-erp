<?php

namespace App\Domain\Biosecurity\Actions;

use App\Domain\Biosecurity\Models\BiosecurityVisit;
use App\Domain\System\Exceptions\DomainException;
use Carbon\CarbonInterface;

class RecordVisitorDeparture
{
    public function __invoke(BiosecurityVisit $visit, ?CarbonInterface $departedAt = null): BiosecurityVisit
    {
        $departedAt ??= now();

        if ($visit->departed_at !== null) {
            throw new DomainException('This visitor has already been signed out.', 'already_departed');
        }

        if ($departedAt->lt($visit->arrived_at) || $departedAt->gt(now()->addMinutes(5))) {
            throw new DomainException('The departure must fall between the arrival and now.', 'departure_time');
        }

        $visit->forceFill(['departed_at' => $departedAt])->save();

        return $visit;
    }
}
