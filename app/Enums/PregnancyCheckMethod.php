<?php

namespace App\Enums;

enum PregnancyCheckMethod: string
{
    case Ultrasound = 'ultrasound';
    case Observation = 'observation';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
