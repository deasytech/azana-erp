<?php

namespace App\Filament\Resources\GoodsReceipts;

use App\Domain\Procurement\Models\GoodsReceipt;
use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ProcurementActions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Deliveries received against purchase orders (created from the order's page). */
class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static ?string $navigationLabel = 'Goods receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.supplier', 'location'])->withCount('lines')->withSum('lines as value_minor', 'value_minor'))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('order.number')->label('Order')->searchable(),
                TextColumn::make('order.supplier.name')->label('Supplier'),
                TextColumn::make('location.name')->label('Store'),
                TextColumn::make('received_on')->date()->sortable(),
                TextColumn::make('delivery_note')->placeholder('-'),
                TextColumn::make('lines_count')->label('Lines'),
                MoneyColumn::make('value_minor', 'Value'),
                IconColumn::make('voided')->label('Voided')->boolean()->getStateUsing(fn (GoodsReceipt $r) => $r->isVoided()),
            ])
            ->recordActions([ProcurementActions::voidReceipt()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListGoodsReceipts::route('/')];
    }
}
