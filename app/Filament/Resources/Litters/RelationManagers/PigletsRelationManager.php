<?php

namespace App\Filament\Resources\Litters\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only list of the individually tracked piglets. */
class PigletsRelationManager extends RelationManager
{
    protected static string $relationship = 'piglets';

    protected static ?string $title = 'Tracked piglets';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('animal.category'))
            ->columns([
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('animal.sex')->label('Sex')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('animal.category.name')->label('Category')->badge(),
                TextColumn::make('birth_weight_kg')->label('Birth kg')->numeric(decimalPlaces: 2)->placeholder('-'),
                TextColumn::make('animal.status')->label('Status')->formatStateUsing(fn ($state) => $state->label()),
            ]);
    }
}
