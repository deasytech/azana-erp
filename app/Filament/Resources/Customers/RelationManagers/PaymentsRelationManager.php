<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Domain\Sales\Models\Payment;
use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    #[On('customer-account-changed')]
    public function refreshPayments(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('allocations'))
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('received_on')->date(),
                MoneyColumn::make('amount_minor', 'Amount'),
                TextColumn::make('method')->formatStateUsing(fn ($state) => $state->label()),
                MoneyColumn::computed('deposit', 'Left as deposit', fn (Payment $r) => $r->unallocatedMinor()),
                IconColumn::make('voided')->label('Voided')->boolean()->getStateUsing(fn (Payment $r) => $r->isVoided()),
            ])
            ->defaultSort('id', 'desc');
    }
}
