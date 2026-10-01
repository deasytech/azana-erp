<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only head-count ledger. */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Head count ledger';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal', 'cause']))
            ->columns([
                TextColumn::make('occurred_on')->date()->sortable(),
                TextColumn::make('type')->formatStateUsing(fn ($state) => $state->label())->badge(),
                TextColumn::make('delta')->label('Pigs')->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : (string) $state),
                TextColumn::make('animal.animal_number')->label('Animal')->placeholder('-'),
                TextColumn::make('cause.name')->label('Cause')->placeholder('-'),
                TextColumn::make('notes')->limit(50)->placeholder('-'),
            ])
            ->defaultSort('occurred_on', 'desc')
            ->paginated([10, 25]);
    }
}
