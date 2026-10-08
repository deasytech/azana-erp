<?php

namespace App\Filament\Resources\Pens;

use App\Domain\Farm\Models\Pen;
use App\Domain\Farm\Models\Room;
use App\Enums\LookupCategory;
use App\Filament\Resources\Pens\Pages\CreatePen;
use App\Filament\Resources\Pens\Pages\EditPen;
use App\Filament\Resources\Pens\Pages\ListPens;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class PenResource extends MasterResource
{
    protected static ?string $model = Pen::class;

    protected static ?string $codePrefix = 'PEN';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'code';

    protected static function fields(): array
    {
        return [
            static::nameField(false),
            Select::make('building_id')->label('Building')->relationship('building', 'name')->required()->preload()->searchable()->live()->afterStateUpdated(fn ($set) => $set('room_id', null)),
            Select::make('room_id')->label('Room')->options(fn ($get) => static::roomOptions($get('building_id')))->searchable()->helperText('Optional. Only rooms of the chosen building are offered.'),
            static::lookupSelect('purpose_id', 'purpose', LookupCategory::PenPurpose, 'Purpose'),
            TextInput::make('capacity')->numeric()->minValue(0)->maxValue(65535)->integer(),
            Textarea::make('notes'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable()->toggleable(),
            TextColumn::make('building.name')->label('Building')->sortable(),
            TextColumn::make('room.name')->label('Room')->placeholder('-'),
            TextColumn::make('purpose.name')->label('Purpose')->badge(),
            TextColumn::make('capacity')->numeric(),
        ];
    }

    protected static function filters(): array
    {
        return [
            SelectFilter::make('building_id')->label('Building')->relationship('building', 'name'),
            SelectFilter::make('purpose_id')->label('Purpose')->relationship('purpose', 'name'),
        ];
    }

    /** @return array<int, string> */
    protected static function roomOptions(mixed $buildingId): array
    {
        return $buildingId ? Room::where('building_id', $buildingId)->orderBy('name')->pluck('name', 'id')->all() : [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPens::route('/'),
            'create' => CreatePen::route('/create'),
            'edit' => EditPen::route('/{record}/edit'),
        ];
    }
}
