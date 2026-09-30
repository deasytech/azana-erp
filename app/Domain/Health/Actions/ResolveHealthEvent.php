<?php

namespace App\Domain\Health\Actions;

use App\Domain\Health\Models\HealthEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\HealthEventStatus;
use Carbon\CarbonInterface;

class ResolveHealthEvent
{
    public function __invoke(HealthEvent $event, CarbonInterface $resolvedOn, ?string $notes = null): HealthEvent
    {
        if (! $event->isOpen()) {
            throw new DomainException('This health case is already resolved.', 'event_resolved');
        }

        if ($resolvedOn->lt($event->observed_on) || $resolvedOn->gt(now()->addMinutes(5))) {
            throw new DomainException('The resolution must fall between the observation date and today.', 'resolved_date');
        }

        $event->forceFill(['status' => HealthEventStatus::Resolved, 'resolved_on' => $resolvedOn, 'resolution_notes' => $notes])->save();

        return $event;
    }
}
