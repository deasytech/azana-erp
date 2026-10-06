<?php

namespace App\Enums;

enum CreditEnforcement: string
{
    case Block = 'block';
    case Warn = 'warn';
    case Off = 'off';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
