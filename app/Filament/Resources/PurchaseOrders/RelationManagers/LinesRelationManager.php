<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Domain\Procurement\Models\PurchaseOrderLine;
use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Items ordered';

    #[On('purchase-order-changed')]
    public function refreshLines(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('item.unit'))
            ->columns([
                TextColumn::make('item.name')->label('Item'),
                TextColumn::make('quantity')->label('Ordered')->numeric(decimalPlaces: 3)->suffix(fn (PurchaseOrderLine $r) => ' '.$r->item->unit->code),
                MoneyColumn::make('unit_cost_minor', 'Cost per unit'),
                MoneyColumn::make('line_total_minor', 'Line total'),
                TextColumn::make('received')->label('Received')->state(fn (PurchaseOrderLine $r) => $r->receivedQuantity()),
                TextColumn::make('outstanding')->label('Outstanding')->state(fn (PurchaseOrderLine $r) => bcsub((string) $r->quantity, $r->receivedQuantity(), 3)),
            ])
            ->paginated(false);
    }
}
