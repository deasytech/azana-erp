<?php

namespace App\Filament\Resources\Litters\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only pre-weaning loss trail. */
class LossesRelationManager extends RelationManager
{
    protected static string $relationship = 'losses';

    protected static ?string $title = 'Pre-weaning losses';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('occurred_on')->date(),
            TextColumn::make('count'),
            TextColumn::make('cause')->placeholder('-'),
            TextColumn::make('animal.animal_number')->label('Animal')->placeholder('-'),
        ]);
    }
}
