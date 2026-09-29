<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

class MoneyInput
{
    /** Decimal entry ("1250.50") stored as integer minor units, never floats. */
    public static function make(string $field, string $label): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->rule('regex:/^\d+(\.\d{1,2})?$/')
            ->formatStateUsing(fn ($state) => $state === null ? null : Money::ofMinor((int) $state, 'XXX')->toDecimal())
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : Money::parse((string) $state, 'XXX')->minor);
    }
}
