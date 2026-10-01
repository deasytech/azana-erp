<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use App\Domain\Production\Actions\VoidBatchWeighIn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class WeighInsRelationManager extends VoidableRecordsRelationManager
{
    protected static string $relationship = 'weighIns';

    protected static ?string $title = 'Weigh-ins';

    protected function recordColumns(): array
    {
        return [
            TextColumn::make('weighed_on')->date()->sortable(),
            TextColumn::make('sample_size')->label('Pigs weighed'),
            TextColumn::make('average_weight_kg')->label('Average (kg)')->numeric(decimalPlaces: 2)->description(fn ($record) => $record->isVoided() ? "Voided: {$record->void_reason}" : null),
        ];
    }

    protected function voidRecord(Model $record, string $reason): void
    {
        app(VoidBatchWeighIn::class)($record, $reason);
    }

    protected function dateColumn(): string
    {
        return 'weighed_on';
    }
}
