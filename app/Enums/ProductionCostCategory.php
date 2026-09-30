<?php

namespace App\Enums;

enum ProductionCostCategory: string
{
    case Medicine = 'medicine';
    case Labour = 'labour';
    case Utilities = 'utilities';
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
