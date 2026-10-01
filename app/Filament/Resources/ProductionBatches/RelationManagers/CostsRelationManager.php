<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use App\Domain\Farm\Models\Farm;
use App\Domain\Production\Actions\VoidProductionCost;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class CostsRelationManager extends VoidableRecordsRelationManager
{
    protected static string $relationship = 'costs';

    protected static ?string $title = 'Other costs';

    protected function recordColumns(): array
    {
        return [
            TextColumn::make('incurred_on')->date()->sortable(),
            TextColumn::make('category')->formatStateUsing(fn ($state) => $state->label())->badge(),
            TextColumn::make('description')->description(fn ($record) => $record->isVoided() ? "Voided: {$record->void_reason}" : null),
            TextColumn::make('amount_minor')->label('Amount')->state(fn ($record) => Money::ofMinor($record->amount_minor, Farm::defaultCurrency())->format()),
        ];
    }

    protected function voidRecord(Model $record, string $reason): void
    {
        app(VoidProductionCost::class)($record, $reason);
    }

    protected function dateColumn(): string
    {
        return 'incurred_on';
    }
}
