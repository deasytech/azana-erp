<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static ?string $title = 'Payments applied';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('payment'))
            ->columns([
                TextColumn::make('payment.number')->label('Payment'),
                TextColumn::make('payment.received_on')->label('Received')->date(),
                TextColumn::make('payment.method')->label('Method')->formatStateUsing(fn ($state) => $state->label()),
                MoneyColumn::make('amount_minor', 'Applied'),
                IconColumn::make('voided')->label('Voided')->boolean()->getStateUsing(fn ($record) => $record->payment->isVoided()),
            ])
            ->paginated(false);
    }
}
