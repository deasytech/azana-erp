<?php

namespace App\Enums;

enum SlaughterRecordStatus: string
{
    case Received = 'received';
    case Rejected = 'rejected';
    case Slaughtered = 'slaughtered';
    case Condemned = 'condemned';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
