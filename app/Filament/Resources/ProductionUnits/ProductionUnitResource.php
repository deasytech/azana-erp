<?php

namespace App\Filament\Resources\ProductionUnits;

use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\ProductionUnit;
use App\Enums\LookupCategory;
use App\Filament\Resources\ProductionUnits\Pages\CreateProductionUnit;
use App\Filament\Resources\ProductionUnits\Pages\EditProductionUnit;
use App\Filament\Resources\ProductionUnits\Pages\ListProductionUnits;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class ProductionUnitResource extends MasterResource
{
    protected static ?string $model = ProductionUnit::class;

    protected static ?string $codePrefix = 'PU';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

    protected static ?int $navigationSort = 20;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('farm_id')->label('Farm')->relationship('farm', 'name')->required()->default(fn () => Farm::orderBy('id')->value('id')),
            static::lookupSelect('type_id', 'type', LookupCategory::ProductionUnitType, 'Type'),
            Textarea::make('description'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('type.name')->label('Type')->badge(),
            TextColumn::make('buildings_count')->counts('buildings')->label('Buildings'),
        ];
    }

    protected static function filters(): array
    {
        return [
            SelectFilter::make('type_id')->label('Type')->relationship('type', 'name'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionUnits::route('/'),
            'create' => CreateProductionUnit::route('/create'),
            'edit' => EditProductionUnit::route('/{record}/edit'),
        ];
    }
}
