<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Domain\Sales\Models\SalesOrderLine;
use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Lines';

    #[On('sales-order-changed')]
    public function refreshLines(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('reservations'))
            ->columns([
                TextColumn::make('description'),
                TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('quantity')->numeric(decimalPlaces: 3)->suffix(fn (SalesOrderLine $r) => " {$r->unit}"),
                MoneyColumn::make('unit_price_minor', 'Price'),
                TextColumn::make('discount_percent')->label('Discount')->suffix('%'),
                MoneyColumn::make('line_total_minor', 'Line total'),
                TextColumn::make('reserved')->label('Reservation')->state(fn (SalesOrderLine $r) => $r->reservations->last()?->status->label() ?? '-'),
            ])
            ->paginated(false);
    }
}
