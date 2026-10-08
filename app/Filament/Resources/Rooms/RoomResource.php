<?php

namespace App\Filament\Resources\Rooms;

use App\Domain\Farm\Models\Room;
use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class RoomResource extends MasterResource
{
    protected static ?string $model = Room::class;

    protected static ?string $codePrefix = 'RM';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

    protected static ?int $navigationSort = 40;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('building_id')->label('Building')->relationship('building', 'name')->required()->preload()->searchable()
                ->disabled(fn (?Room $record) => $record?->isInUse() ?? false)
                ->helperText('Locked once the room has pens or locations.'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('building.name')->label('Building'),
            TextColumn::make('pens_count')->counts('pens')->label('Pens'),
        ];
    }

    protected static function filters(): array
    {
        return [
            SelectFilter::make('building_id')->label('Building')->relationship('building', 'name'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/create'),
            'edit' => EditRoom::route('/{record}/edit'),
        ];
    }
}
