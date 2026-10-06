<?php

namespace App\Enums;

enum CreditStatus: string
{
    case None = 'none';
    case Approved = 'approved';
    case OnHold = 'on_hold';
    case Blocked = 'blocked';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
