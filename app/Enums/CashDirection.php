<?php

namespace App\Enums;

enum CashDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
