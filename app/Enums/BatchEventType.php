<?php

namespace App\Enums;

enum BatchEventType: string
{
    case Placement = 'placement';
    case TransferIn = 'transfer_in';
    case Mortality = 'mortality';
    case Cull = 'cull';
    case Sale = 'sale';
    case Slaughter = 'slaughter';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }

    /** Events that add pigs (their delta is positive). */
    public function adds(): bool
    {
        return $this === self::Placement || $this === self::TransferIn;
    }

    /** Events that remove pigs (their delta is negative). */
    public function removes(): bool
    {
        return ! $this->adds() && $this !== self::Adjustment;
    }
}
