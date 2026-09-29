<?php

namespace App\Enums;

enum AnimalStatus: string
{
    case Active = 'active';
    case Sold = 'sold';
    case Dead = 'dead';
    case Culled = 'culled';
    case Slaughtered = 'slaughtered';
    case TransferredOut = 'transferred_out';

    /** Terminal statuses end the animal's presence on the farm; there is no way back. */
    public function isTerminal(): bool
    {
        return $this !== self::Active;
    }

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
