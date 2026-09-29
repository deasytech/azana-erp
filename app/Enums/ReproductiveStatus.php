<?php

namespace App\Enums;

/** A breeding female's current position in the reproductive cycle (derived, never stored). */
enum ReproductiveStatus: string
{
    case Open = 'open';
    case Served = 'served';
    case Pregnant = 'pregnant';
    case Lactating = 'lactating';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
