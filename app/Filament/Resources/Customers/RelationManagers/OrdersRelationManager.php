<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\MoneyColumn;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('ordered_on')->date(),
                MoneyColumn::make('total_minor', 'Total'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        SalesOrderStatus::Dispatched => 'success', SalesOrderStatus::Confirmed => 'warning', SalesOrderStatus::Cancelled => 'danger', default => 'gray',
                    }),
            ])
            ->recordActions([ViewAction::make()->url(fn ($record) => SalesOrderResource::getUrl('view', ['record' => $record]))])
            ->defaultSort('id', 'desc');
    }
}
