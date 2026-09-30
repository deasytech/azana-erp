<?php

namespace App\Enums;

enum QuarantineType: string
{
    case Quarantine = 'quarantine';
    case Isolation = 'isolation';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
