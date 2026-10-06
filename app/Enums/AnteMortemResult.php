<?php

namespace App\Enums;

enum AnteMortemResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
