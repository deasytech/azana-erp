<?php

namespace App\Enums;

enum AnimalSource: string
{
    case BornOnFarm = 'born_on_farm';
    case Purchased = 'purchased';
    case TransferredIn = 'transferred_in';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
