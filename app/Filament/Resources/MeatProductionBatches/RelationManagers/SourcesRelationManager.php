<?php

namespace App\Filament\Resources\MeatProductionBatches\RelationManagers;

use App\Domain\Slaughter\Models\Carcass;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Where the meat came from: each carcass, the slaughter day and the animal or batch behind it. */
class SourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'carcasses';

    protected static ?string $title = 'Traced to';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['record.batch', 'record.animal', 'record.productionBatch']))
            ->columns([
                TextColumn::make('number')->label('Carcass'),
                TextColumn::make('record.batch.number')->label('Slaughter day'),
                TextColumn::make('source')->label('Pig')->state(fn (Carcass $c) => $c->record->sourceLabel()),
                TextColumn::make('live_weight_kg')->label('Live (kg)'),
                TextColumn::make('hot_weight_kg')->label('Carcass (kg)'),
                TextColumn::make('dressing_percent')->label('Dressing')->suffix('%'),
            ])
            ->paginated(false);
    }
}
