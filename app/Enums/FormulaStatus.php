<?php

namespace App\Enums;

enum FormulaStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
