<?php

namespace App\Enums;

enum TaskCategory: string
{
    case General = 'general';
    case Rounds = 'rounds';
    case Health = 'health';
    case Breeding = 'breeding';
    case Inventory = 'inventory';
    case Production = 'production';
    case Sales = 'sales';
    case Finance = 'finance';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
