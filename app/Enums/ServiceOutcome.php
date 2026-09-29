<?php

namespace App\Enums;

enum ServiceOutcome: string
{
    case Pending = 'pending';
    case Pregnant = 'pregnant';
    case NotPregnant = 'not_pregnant';
    case Farrowed = 'farrowed';
    case Aborted = 'aborted';

    /** Outcomes where the sow may still farrow from this service. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Pregnant;
    }

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
