<?php

namespace App\Filament\Support;

use App\Domain\Farm\Models\Farm;
use App\Support\Money;
use Closure;
use Filament\Tables\Columns\TextColumn;

/** Table columns showing integer minor units as money in the farm currency. */
class MoneyColumn
{
    /** A column bound to a minor-unit attribute (sortable like any other). */
    public static function make(string $field, string $label): TextColumn
    {
        return TextColumn::make($field)->label($label)->formatStateUsing(fn ($state) => static::format($state));
    }

    /** A column whose minor-unit value is computed from the record. */
    public static function computed(string $name, string $label, Closure $minor): TextColumn
    {
        return TextColumn::make($name)->label($label)->state(fn ($record) => static::format($minor($record)));
    }

    public static function format(int|string|null $minor): string
    {
        return $minor === null ? '-' : Money::ofMinor((int) $minor, Farm::defaultCurrency())->format();
    }
}
