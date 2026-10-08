<?php

namespace App\Enums;

enum ListingKind: string
{
    case Pigs = 'pigs';
    case Semen = 'semen';
    case Meat = 'meat';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Pigs => 'Live pigs',
            self::Semen => 'Boar semen',
            self::Meat => 'Pork and meat products',
            self::Service => 'Services',
        };
    }
}
