<?php

namespace App\Enums;

enum CullHealthStatus: string
{
    case Healthy = 'healthy';
    case Sick = 'sick';
    case Injured = 'injured';
    case PoorCondition = 'poor_condition';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
