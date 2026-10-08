<?php

namespace App\Filament\Resources\InventoryLocations;

use App\Domain\Farm\Models\Location;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Filament\Resources\InventoryLocations\Pages\CreateInventoryLocation;
use App\Filament\Resources\InventoryLocations\Pages\EditInventoryLocation;
use App\Filament\Resources\InventoryLocations\Pages\ListInventoryLocations;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

/** Stores, silos, cold rooms and shelves where stock is kept. */
class InventoryLocationResource extends MasterResource
{
    protected static ?string $model = InventoryLocation::class;

    protected static ?string $codePrefix = 'STORE';

    protected static ?string $navigationLabel = 'Stores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 60;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('location_id')->label('Farm location')->searchable()
                ->options(fn () => Location::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->helperText('Optional: where this store is on the farm.'),
            Textarea::make('notes'),
        ];
    }

    protected static function columns(): array
    {
        return [TextColumn::make('name')->searchable(), TextColumn::make('farmLocation.name')->label('Farm location')->placeholder('-')];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryLocations::route('/'),
            'create' => CreateInventoryLocation::route('/create'),
            'edit' => EditInventoryLocation::route('/{record}/edit'),
        ];
    }
}
