<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Domain\Sales\Models\Invoice;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\MoneyColumn;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    #[On('customer-account-changed')]
    public function refreshInvoices(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('allocations.payment'))
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('issued_on')->date(),
                TextColumn::make('due_on')->date()->color(fn (Invoice $r) => $r->isOverdue() ? 'danger' : null),
                MoneyColumn::make('total_minor', 'Total'),
                MoneyColumn::computed('balance', 'Balance', fn (Invoice $r) => $r->balanceMinor()),
            ])
            ->recordActions([ViewAction::make()->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record]))])
            ->defaultSort('id', 'desc');
    }
}
