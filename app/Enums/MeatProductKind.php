<?php

namespace App\Enums;

enum MeatProductKind: string
{
    case WholeCarcass = 'whole_carcass';
    case PrimaryCut = 'primary_cut';
    case Offal = 'offal';
    case ByProduct = 'by_product';

    public function label(): string
    {
        return match ($this) {
            self::WholeCarcass => 'Whole carcass',
            self::PrimaryCut => 'Primary cut',
            self::Offal => 'Offal',
            self::ByProduct => 'By-product',
        };
    }
}
