<?php

namespace App\Enums;

enum JournalStatus: string
{
    case Posted = 'posted';
    case Pending = 'pending';
    case Rejected = 'rejected';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
