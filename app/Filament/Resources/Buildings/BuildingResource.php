<?php

namespace App\Filament\Resources\Buildings;

use App\Domain\Farm\Models\Building;
use App\Enums\LookupCategory;
use App\Filament\Resources\Buildings\Pages\CreateBuilding;
use App\Filament\Resources\Buildings\Pages\EditBuilding;
use App\Filament\Resources\Buildings\Pages\ListBuildings;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class BuildingResource extends MasterResource
{
    protected static ?string $model = Building::class;

    protected static ?string $codePrefix = 'BLD';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

    protected static ?int $navigationSort = 30;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('production_unit_id')->label('Production unit')->relationship('productionUnit', 'name')->required()->preload()->searchable()
                ->disabled(fn (?Building $record) => $record?->locations()->exists() ?? false)
                ->helperText('Locked once the building has locations.'),
            static::lookupSelect('type_id', 'type', LookupCategory::BuildingType, 'Type'),
            Textarea::make('description'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('productionUnit.name')->label('Unit'),
            TextColumn::make('type.name')->label('Type')->badge(),
            TextColumn::make('pens_count')->counts('pens')->label('Pens'),
        ];
    }

    protected static function filters(): array
    {
        return [
            SelectFilter::make('production_unit_id')->label('Production unit')->relationship('productionUnit', 'name'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuildings::route('/'),
            'create' => CreateBuilding::route('/create'),
            'edit' => EditBuilding::route('/{record}/edit'),
        ];
    }
}
