<?php

namespace App\Filament\Resources\Animals\RelationManagers;

use App\Domain\Animal\Models\Animal;
use App\Filament\Resources\Litters\LitterResource;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** A sow's litters (shown only for breeding females). */
class LittersRelationManager extends RelationManager
{
    protected static string $relationship = 'litters';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Animal && $ownerRecord->isBreedingFemale() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('litter_number'),
                TextColumn::make('born_on')->date(),
                TextColumn::make('farrowing.born_alive')->label('Born alive'),
                TextColumn::make('weaning.weaned_count')->label('Weaned')->placeholder('-'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
            ])
            ->recordActions([ViewAction::make()->url(fn ($record) => LitterResource::getUrl('view', ['record' => $record]))]);
    }
}
