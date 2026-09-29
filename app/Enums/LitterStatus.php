<?php

namespace App\Enums;

enum LitterStatus: string
{
    case Suckling = 'suckling';
    case Weaned = 'weaned';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
