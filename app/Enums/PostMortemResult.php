<?php

namespace App\Enums;

enum PostMortemResult: string
{
    case Passed = 'passed';
    case Partial = 'partial';
    case Condemned = 'condemned';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Fit for food',
            self::Partial => 'Partly condemned',
            self::Condemned => 'Condemned',
        };
    }
}
