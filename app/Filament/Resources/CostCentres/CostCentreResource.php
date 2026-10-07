<?php

namespace App\Filament\Resources\CostCentres;

use App\Domain\Finance\Models\CostCentre;
use App\Filament\Resources\CostCentres\Pages\CreateCostCentre;
use App\Filament\Resources\CostCentres\Pages\EditCostCentre;
use App\Filament\Resources\CostCentres\Pages\ListCostCentres;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

/** The business units costs and revenue are charged to (breeding, grower, feed mill...). */
class CostCentreResource extends MasterResource
{
    protected static ?string $model = CostCentre::class;

    protected static ?string $navigationLabel = 'Cost centres';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 91;

    protected static int $codeLength = 20;

    protected static function fields(): array
    {
        return [static::nameField()];
    }

    protected static function columns(): array
    {
        return [TextColumn::make('name')->searchable()];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCostCentres::route('/'),
            'create' => CreateCostCentre::route('/create'),
            'edit' => EditCostCentre::route('/{record}/edit'),
        ];
    }
}
