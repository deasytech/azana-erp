<?php

namespace App\Enums;

enum MeatProductionStatus: string
{
    case Produced = 'produced';
    case Reversed = 'reversed';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
