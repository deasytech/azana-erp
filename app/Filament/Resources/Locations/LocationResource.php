<?php

namespace App\Filament\Resources\Locations;

use App\Domain\Farm\Models\Building;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\Room;
use App\Enums\LookupCategory;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class LocationResource extends MasterResource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

    protected static ?int $navigationSort = 60;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('production_unit_id')->label('Production unit')->relationship('productionUnit', 'name')->required()->preload()->searchable()->live()->afterStateUpdated(function ($set) {
                $set('building_id', null);
                $set('room_id', null);
            }),
            Select::make('building_id')->label('Building')->options(fn ($get) => static::buildingOptions($get('production_unit_id')))->searchable()->live()->afterStateUpdated(fn ($set) => $set('room_id', null)),
            Select::make('room_id')->label('Room')->options(fn ($get) => static::roomOptions($get('building_id')))->searchable(),
            static::lookupSelect('type_id', 'type', LookupCategory::LocationType, 'Type'),
            Textarea::make('description'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('productionUnit.name')->label('Unit'),
            TextColumn::make('building.name')->label('Building')->placeholder('-'),
            TextColumn::make('type.name')->label('Type')->badge(),
        ];
    }

    protected static function filters(): array
    {
        return [
            SelectFilter::make('production_unit_id')->label('Production unit')->relationship('productionUnit', 'name'),
            SelectFilter::make('type_id')->label('Type')->relationship('type', 'name'),
        ];
    }

    /** @return array<int, string> */
    protected static function buildingOptions(mixed $unitId): array
    {
        return $unitId ? Building::where('production_unit_id', $unitId)->orderBy('name')->pluck('name', 'id')->all() : [];
    }

    /** @return array<int, string> */
    protected static function roomOptions(mixed $buildingId): array
    {
        return $buildingId ? Room::where('building_id', $buildingId)->orderBy('name')->pluck('name', 'id')->all() : [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }
}
