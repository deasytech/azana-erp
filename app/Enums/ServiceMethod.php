<?php

namespace App\Enums;

enum ServiceMethod: string
{
    case Natural = 'natural';
    case ArtificialInsemination = 'artificial_insemination';

    public function label(): string
    {
        return $this === self::Natural ? 'Natural mating' : 'Artificial insemination (AI)';
    }
}
