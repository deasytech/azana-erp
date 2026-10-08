<?php

namespace App\Filament\Resources\InventoryItems;

use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Enums\InventoryCategory;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

/** Everything the farm keeps in stock: feed, medicine, consumables, spares. */
class InventoryItemResource extends MasterResource
{
    protected static ?string $model = InventoryItem::class;

    protected static ?string $codePrefix = 'ITM';

    protected static ?string $navigationLabel = 'Items';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 50;

    protected static int $codeLength = 40;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('category')->options(AnimalResource::enumOptions(InventoryCategory::cases()))->required(),
            Select::make('unit_id')->label('Unit')->required()->searchable()
                ->options(fn () => UnitOfMeasure::where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->code})"])->all()),
            Toggle::make('tracks_batches')->label('Track by batch / lot')->live(),
            Toggle::make('tracks_expiry')->label('Has an expiry date')->live()
                ->afterStateUpdated(fn ($state, $set) => $state ? $set('tracks_batches', true) : null)
                ->helperText('Expiry belongs to a batch, so this also tracks by batch.'),
            TextInput::make('reorder_level')->numeric()->minValue(0)->step(0.001)->helperText('Alert when stock across all stores falls to this quantity.'),
            TextInput::make('reorder_quantity')->numeric()->minValue(0.001)->step(0.001)->helperText('Usual quantity to order.'),
            Select::make('feed_type_id')->label('Feed type')->searchable()
                ->options(fn () => FeedType::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->unique(ignoreRecord: true)
                ->helperText('Link finished feed to its feed type so feed records can be taken from stock.'),
            Textarea::make('description'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('category')->badge()->formatStateUsing(fn ($state) => $state->label()),
            TextColumn::make('unit.code')->label('Unit'),
            IconColumn::make('tracks_batches')->label('Batches')->boolean(),
            IconColumn::make('tracks_expiry')->label('Expires')->boolean(),
            TextColumn::make('reorder_level')->label('Reorder at')->numeric(decimalPlaces: 3)->placeholder('-'),
        ];
    }

    protected static function filters(): array
    {
        return [SelectFilter::make('category')->options(AnimalResource::enumOptions(InventoryCategory::cases()))];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
