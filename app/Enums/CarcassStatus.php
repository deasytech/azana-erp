<?php

namespace App\Enums;

enum CarcassStatus: string
{
    case Hanging = 'hanging';
    case Processed = 'processed';
    case Condemned = 'condemned';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
