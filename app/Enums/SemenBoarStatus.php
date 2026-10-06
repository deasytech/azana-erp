<?php

namespace App\Enums;

enum SemenBoarStatus: string
{
    case Active = 'active';
    case Resting = 'resting';
    case Retired = 'retired';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
