<?php

namespace App\Enums;

enum DisposalType: string
{
    case Sold = 'sold';
    case Slaughtered = 'slaughtered';
    case Destroyed = 'destroyed';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }

    /** Disposals where the animal may enter the food chain, so withdrawal periods must be respected. */
    public function entersFoodChain(): bool
    {
        return $this === self::Sold || $this === self::Slaughtered;
    }
}
