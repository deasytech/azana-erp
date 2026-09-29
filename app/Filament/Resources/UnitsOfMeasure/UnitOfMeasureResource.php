<?php

namespace App\Filament\Resources\UnitsOfMeasure;

use App\Domain\Farm\Models\UnitOfMeasure;
use App\Filament\Resources\UnitsOfMeasure\Pages\CreateUnitOfMeasure;
use App\Filament\Resources\UnitsOfMeasure\Pages\EditUnitOfMeasure;
use App\Filament\Resources\UnitsOfMeasure\Pages\ListUnitsOfMeasure;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class UnitOfMeasureResource extends MasterResource
{
    protected static ?string $model = UnitOfMeasure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 30;

    protected static int $codeLength = 20;

    protected static function fields(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(60),
            Select::make('category')->options(static::CATEGORIES)->required(),
            Select::make('base_unit_id')->label('Base unit')
                ->relationship('baseUnit', 'name', modifyQueryUsing: fn ($query) => $query->whereNull('base_unit_id'), ignoreRecord: true)
                ->searchable()->preload()->live()
                ->rule(fn (?UnitOfMeasure $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    if ($value && ($value == $record?->id || UnitOfMeasure::where('id', $value)->whereNotNull('base_unit_id')->exists())) {
                        $fail('The base unit must be an existing base unit, and cannot be the unit itself.');
                    }
                })
                ->disabled(fn (?UnitOfMeasure $record) => $record !== null && UnitOfMeasure::where('base_unit_id', $record->id)->exists())
                ->helperText('Only base units (such as kg or litre) can be chosen. Leave empty to make this a base unit; units that others are based on must stay base units.'),
            TextInput::make('conversion_factor')->numeric()->rule('gt:0')->requiredWith('base_unit_id')->helperText('How many base units make one of this unit (e.g. 1000 for a tonne when the base is kg).'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('category')->badge(),
            TextColumn::make('baseUnit.code')->label('Base')->placeholder('-'),
            TextColumn::make('conversion_factor')->placeholder('-'),
        ];
    }

    protected static function filters(): array
    {
        return [
            SelectFilter::make('category')->options(static::CATEGORIES),
        ];
    }

    private const CATEGORIES = ['mass' => 'Mass', 'volume' => 'Volume', 'count' => 'Count', 'length' => 'Length', 'other' => 'Other'];

    public static function getPages(): array
    {
        return [
            'index' => ListUnitsOfMeasure::route('/'),
            'create' => CreateUnitOfMeasure::route('/create'),
            'edit' => EditUnitOfMeasure::route('/{record}/edit'),
        ];
    }
}
