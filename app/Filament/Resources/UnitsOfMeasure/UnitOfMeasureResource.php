<?php

namespace App\Filament\Resources\UnitsOfMeasure;

use App\Domain\Farm\Models\UnitOfMeasure;
use App\Filament\Resources\UnitsOfMeasure\Pages\CreateUnitOfMeasure;
use App\Filament\Resources\UnitsOfMeasure\Pages\EditUnitOfMeasure;
use App\Filament\Resources\UnitsOfMeasure\Pages\ListUnitsOfMeasure;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class UnitOfMeasureResource extends Resource
{
    protected static ?string $model = UnitOfMeasure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(20)->unique(ignoreRecord: true)->helperText('Unique business identifier; stored in upper case.'),
            TextInput::make('name')->required()->maxLength(60),
            Select::make('category')->options(['mass' => 'Mass', 'volume' => 'Volume', 'count' => 'Count', 'length' => 'Length', 'other' => 'Other'])->required(),
            Select::make('base_unit_id')->label('Base unit')->relationship('baseUnit', 'name')->searchable()->preload()->live()->helperText('Leave empty for a base unit such as kg or litre.'),
            TextInput::make('conversion_factor')->numeric()->minValue(0)->requiredWith('base_unit_id')->helperText('How many base units make one of this unit (e.g. 1000 for a tonne when the base is kg).'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('category')->badge(),
                TextColumn::make('baseUnit.code')->label('Base')->placeholder('-'),
                TextColumn::make('conversion_factor')->placeholder('-'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->options(['mass' => 'Mass', 'volume' => 'Volume', 'count' => 'Count', 'length' => 'Length', 'other' => 'Other']),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitsOfMeasure::route('/'),
            'create' => CreateUnitOfMeasure::route('/create'),
            'edit' => EditUnitOfMeasure::route('/{record}/edit'),
        ];
    }
}
