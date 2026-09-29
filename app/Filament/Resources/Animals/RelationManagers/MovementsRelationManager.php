<?php

namespace App\Filament\Resources\Animals\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only: movements are permanent and are added through the Move action. */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fromPen', 'fromLocation', 'toPen', 'toLocation', 'reason', 'user']))
            ->columns([
                TextColumn::make('moved_at')->dateTime()->label('When'),
                TextColumn::make('from')->state(fn ($r) => $r->fromLabel()),
                TextColumn::make('to')->state(fn ($r) => $r->toLabel()),
                TextColumn::make('reason.name')->placeholder('-'),
                TextColumn::make('notes')->limit(40)->placeholder('-'),
                TextColumn::make('user.name')->label('By')->placeholder('-'),
            ])
            ->paginated([10, 25]);
    }
}
