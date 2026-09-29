<?php

namespace App\Filament\Resources\Locations;

use App\Domain\Farm\Models\Building;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\Room;
use App\Enums\LookupCategory;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Filament\Resources\Locations\Pages\ListLocations;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(30)->unique(ignoreRecord: true)->helperText('Unique business identifier; stored in upper case.'),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('production_unit_id')->label('Production unit')->relationship('productionUnit', 'name')->required()->preload()->searchable()->live()->afterStateUpdated(function ($set) {
                $set('building_id', null);
                $set('room_id', null);
            }),
            Select::make('building_id')->label('Building')->options(fn ($get) => $get('production_unit_id') ? Building::where('production_unit_id', $get('production_unit_id'))->orderBy('name')->pluck('name', 'id') : [])->searchable()->live()->afterStateUpdated(fn ($set) => $set('room_id', null)),
            Select::make('room_id')->label('Room')->options(fn ($get) => $get('building_id') ? Room::where('building_id', $get('building_id'))->orderBy('name')->pluck('name', 'id') : [])->searchable(),
            Select::make('type_id')->label('Type')->relationship('type', 'name', modifyQueryUsing: fn ($query) => $query->where('category', LookupCategory::LocationType->value)->where('is_active', true)->orderBy('sort_order'))->required()->preload()->searchable(),
            Textarea::make('description'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('productionUnit.name')->label('Unit'),
                TextColumn::make('building.name')->label('Building')->placeholder('-'),
                TextColumn::make('type.name')->label('Type')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('production_unit_id')->label('Production unit')->relationship('productionUnit', 'name'),
                SelectFilter::make('type_id')->label('Type')->relationship('type', 'name'),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
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
