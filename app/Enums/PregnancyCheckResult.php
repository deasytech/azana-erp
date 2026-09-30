<?php

namespace App\Enums;

enum PregnancyCheckResult: string
{
    case Positive = 'positive';
    case Negative = 'negative';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
