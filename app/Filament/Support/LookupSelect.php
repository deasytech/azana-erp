<?php

namespace App\Filament\Support;

use App\Domain\Farm\Models\LookupValue;
use App\Enums\LookupCategory;
use Filament\Forms\Components\Select;

class LookupSelect
{
    /** Select bound to a lookup_values category (only active values are offered). */
    public static function make(string $field, string $relation, LookupCategory $category, string $label): Select
    {
        return Select::make($field)
            ->label($label)
            ->relationship($relation, 'name', modifyQueryUsing: fn ($query) => $query
                ->where('category', $category->value)->where('is_active', true)->orderBy('sort_order'))
            ->preload()
            ->searchable();
    }

    /** Plain options select for forms without a model relationship (e.g. action modals). */
    public static function options(string $field, LookupCategory $category, string $label): Select
    {
        return Select::make($field)
            ->label($label)
            ->options(fn () => LookupValue::inCategory($category)->pluck('name', 'id')->all())
            ->searchable();
    }
}
