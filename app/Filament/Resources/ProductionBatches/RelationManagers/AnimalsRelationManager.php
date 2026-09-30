<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only: individually tracked animals that are or were in the batch. */
class AnimalsRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Tracked animals';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('animal'))
            ->columns([
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('joined_on')->date(),
                TextColumn::make('left_on')->date()->placeholder('Still in'),
                TextColumn::make('left_reason')->formatStateUsing(fn ($state) => $state ? str($state)->replace('_', ' ')->ucfirst()->toString() : null)->placeholder('-'),
            ])
            ->paginated([10, 25]);
    }
}
