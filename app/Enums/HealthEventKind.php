<?php

namespace App\Enums;

enum HealthEventKind: string
{
    case Illness = 'illness';
    case Injury = 'injury';
    case Observation = 'observation';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
