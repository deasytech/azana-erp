<?php

namespace App\Enums;

enum EnquiryKind: string
{
    case Pigs = 'pigs';
    case Semen = 'semen';
    case Meat = 'meat';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Pigs => 'Live pigs',
            self::Semen => 'Boar semen',
            self::Meat => 'Pork and meat products',
            self::General => 'Something else',
        };
    }
}
