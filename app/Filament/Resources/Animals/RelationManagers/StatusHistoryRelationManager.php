<?php

namespace App\Filament\Resources\Animals\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only status trail. */
class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $title = 'Status history';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('changed_at')->dateTime()->label('When'),
                TextColumn::make('from_status')->formatStateUsing(fn ($state) => $state?->label())->placeholder('-'),
                TextColumn::make('to_status')->formatStateUsing(fn ($state) => $state->label())->badge(),
                TextColumn::make('reason')->limit(60),
                TextColumn::make('user.name')->label('By')->placeholder('-'),
            ])
            ->paginated([10, 25]);
    }
}
