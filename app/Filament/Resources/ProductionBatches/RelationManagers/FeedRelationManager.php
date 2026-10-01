<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use App\Domain\Farm\Models\Farm;
use App\Domain\Feed\Actions\VoidFeedConsumption;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class FeedRelationManager extends VoidableRecordsRelationManager
{
    protected static string $relationship = 'feedRecords';

    protected static ?string $title = 'Feed';

    protected function recordColumns(): array
    {
        return [
            TextColumn::make('consumed_on')->date()->sortable(),
            TextColumn::make('feedType.name')->label('Feed'),
            TextColumn::make('quantity_kg')->label('kg')->numeric(decimalPlaces: 2)->description(fn ($record) => $record->isVoided() ? "Voided: {$record->void_reason}" : null),
            TextColumn::make('cost_minor')->label('Cost')->state(fn ($record) => $record->cost_minor === null ? '-' : Money::ofMinor($record->cost_minor, Farm::defaultCurrency())->format()),
        ];
    }

    protected function voidRecord(Model $record, string $reason): void
    {
        app(VoidFeedConsumption::class)($record, $reason);
    }

    protected function dateColumn(): string
    {
        return 'consumed_on';
    }
}
