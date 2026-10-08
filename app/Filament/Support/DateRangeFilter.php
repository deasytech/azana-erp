<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/** A "from - until" filter on one date column, with the chosen range shown as indicators above the table. */
class DateRangeFilter
{
    public static function make(string $column, string $label = 'Date'): Filter
    {
        return Filter::make($column.'_range')
            ->label($label)
            ->schema([
                DatePicker::make('from')->label($label.' from'),
                DatePicker::make('until')->label($label.' until'),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate($column, '<=', $date)))
            ->indicateUsing(fn (array $data): array => array_values(array_filter([
                ($data['from'] ?? null) ? $label.' from '.date('d M Y', strtotime($data['from'])) : null,
                ($data['until'] ?? null) ? $label.' until '.date('d M Y', strtotime($data['until'])) : null,
            ])));
    }
}
