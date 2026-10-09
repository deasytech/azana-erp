<?php

namespace App\Filament\Resources\DataImports\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Each row of the file and what the check found. Failed rows first. */
class RowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    protected static ?string $title = 'Rows';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('Sheet row')->sortable(),
                TextColumn::make('error')->label('Problem')->placeholder('Passed')->color('danger')->wrap(),
                TextColumn::make('data')->label('What the row says')->formatStateUsing(fn ($state) => collect($state)->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode('; '))->limit(120)->tooltip(fn ($state) => collect($state)->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n")),
            ])
            ->filters([TernaryFilter::make('failed')->label('Rows with problems')->queries(
                true: fn (Builder $q) => $q->whereNotNull('error'), false: fn (Builder $q) => $q->whereNull('error'), blank: fn (Builder $q) => $q,
            )->default(null)])
            ->defaultSort('row_number')
            ->paginated([25, 50, 100]);
    }
}
