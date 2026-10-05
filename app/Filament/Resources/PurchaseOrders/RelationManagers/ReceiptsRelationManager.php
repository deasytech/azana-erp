<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Domain\Procurement\Models\GoodsReceipt;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ProcurementActions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'receipts';

    protected static ?string $title = 'Goods received';

    public function isReadOnly(): bool
    {
        return false;
    }

    #[On('purchase-order-changed')]
    public function refreshReceipts(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('location')->withCount('lines')->withSum('lines as value_minor', 'value_minor'))
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('received_on')->date(),
                TextColumn::make('location.name')->label('Store'),
                TextColumn::make('delivery_note')->placeholder('-'),
                TextColumn::make('lines_count')->label('Lines'),
                MoneyColumn::make('value_minor', 'Value'),
                IconColumn::make('voided')->label('Voided')->boolean()->getStateUsing(fn (GoodsReceipt $r) => $r->isVoided()),
            ])
            ->recordActions([ProcurementActions::voidReceipt()->after(fn () => $this->dispatch('purchase-order-changed'))])
            ->defaultSort('id', 'desc');
    }
}
