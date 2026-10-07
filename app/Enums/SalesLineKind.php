<?php

namespace App\Enums;

enum SalesLineKind: string
{
    case Semen = 'semen';
    case PigAnimal = 'pig_animal';
    case PigBatch = 'pig_batch';
    case Meat = 'meat';

    public function label(): string
    {
        return match ($this) {
            self::Semen => 'Semen',
            self::PigAnimal => 'Pig (tracked animal)',
            self::PigBatch => 'Pigs from a batch',
            self::Meat => 'Meat',
        };
    }
}
