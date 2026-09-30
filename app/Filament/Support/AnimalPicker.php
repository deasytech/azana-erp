<?php

namespace App\Filament\Support;

use App\Domain\Animal\Models\Animal;
use App\Enums\AnimalStatus;
use Filament\Forms\Components\Select;

/** Searchable pickers for animals of a given category (active animals only). */
class AnimalPicker
{
    /** @param list<string> $categoryCodes */
    public static function make(string $field, string $label, array $categoryCodes): Select
    {
        $query = fn () => Animal::where('status', AnimalStatus::Active->value)
            ->whereHas('category', fn ($q) => $q->whereIn('code', $categoryCodes));

        return Select::make($field)->label($label)->searchable()
            ->getSearchResultsUsing(fn (string $search) => $query()->where('animal_number', 'like', '%'.strtoupper($search).'%')->orderBy('animal_number')->limit(30)->pluck('animal_number', 'id')->all())
            ->getOptionLabelUsing(fn ($value) => Animal::find($value)?->animal_number);
    }

    /** Any active animal, whatever its category. */
    public static function any(string $field = 'animal_id'): Select
    {
        return Select::make($field)->label('Animal')->searchable()
            ->getSearchResultsUsing(fn (string $search) => Animal::where('status', AnimalStatus::Active->value)->where('animal_number', 'like', '%'.strtoupper($search).'%')->orderBy('animal_number')->limit(30)->pluck('animal_number', 'id')->all())
            ->getOptionLabelUsing(fn ($value) => Animal::find($value)?->animal_number);
    }

    public static function sow(string $field = 'sow_id'): Select
    {
        return self::make($field, 'Sow / gilt', ['sow', 'gilt']);
    }

    public static function boar(string $field = 'boar_id'): Select
    {
        return self::make($field, 'Boar', ['boar']);
    }
}
