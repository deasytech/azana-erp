<?php

namespace App\Filament\Resources\MeatProducts;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Meat\Models\MeatProduct;
use App\Enums\InventoryCategory;
use App\Enums\MeatProductKind;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\MeatProducts\Pages\CreateMeatProduct;
use App\Filament\Resources\MeatProducts\Pages\EditMeatProduct;
use App\Filament\Resources\MeatProducts\Pages\ListMeatProducts;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

/** What comes off a carcass: each product is stocked as its own item (in kg) and expires after its shelf life. */
class MeatProductResource extends MasterResource
{
    protected static ?string $model = MeatProduct::class;

    protected static ?string $navigationLabel = 'Product catalogue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Slaughter & meat';

    protected static ?int $navigationSort = 50;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('kind')->options(AnimalResource::enumOptions(MeatProductKind::cases()))->required(),
            Select::make('inventory_item_id')->label('Stock item')->required()->searchable()->unique(ignoreRecord: true)
                ->options(fn () => InventoryItem::where('category', InventoryCategory::Meat)->where('is_active', true)->where('tracks_batches', true)->whereHas('unit', fn ($q) => $q->where('code', 'KG'))->orderBy('name')->pluck('name', 'id'))
                ->helperText('A meat item counted in kg and tracked by batch; make one under Inventory > Items first.'),
            TextInput::make('shelf_life_days')->label('Use-by after (days)')->numeric()->integer()->minValue(1)->maxValue(730)->required(),
            TextInput::make('storage_note')->maxLength(255)->helperText('For example: keep at 0-4 C.'),
            Textarea::make('description'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => $state->label()),
            TextColumn::make('item.code')->label('Stock item'),
            TextColumn::make('shelf_life_days')->label('Use-by (days)'),
        ];
    }

    protected static function filters(): array
    {
        return [SelectFilter::make('kind')->options(AnimalResource::enumOptions(MeatProductKind::cases()))];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeatProducts::route('/'),
            'create' => CreateMeatProduct::route('/create'),
            'edit' => EditMeatProduct::route('/{record}/edit'),
        ];
    }
}
