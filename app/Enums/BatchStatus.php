<?php

namespace App\Enums;

enum BatchStatus: string
{
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
