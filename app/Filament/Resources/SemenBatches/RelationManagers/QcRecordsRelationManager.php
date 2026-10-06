<?php

namespace App\Filament\Resources\SemenBatches\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class QcRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'qcRecords';

    protected static ?string $title = 'Laboratory QC';

    #[On('semen-batch-changed')]
    public function refreshQc(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('evaluatedBy'))
            ->columns([
                TextColumn::make('evaluated_at')->dateTime()->sortable(),
                TextColumn::make('motility_percent')->label('Motility')->suffix('%'),
                TextColumn::make('concentration_million_per_ml')->label('Concentration (million/ml)'),
                TextColumn::make('abnormal_percent')->label('Abnormal forms')->suffix('%'),
                IconColumn::make('passed')->boolean(),
                TextColumn::make('failed_because')->label('Why it failed')->placeholder('-')->wrap(),
                TextColumn::make('evaluatedBy.name')->label('Analyst')->placeholder('-'),
                TextColumn::make('notes')->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->paginated(false);
    }
}
