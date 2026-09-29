<?php

namespace App\Filament\Resources\Pens;

use App\Domain\Farm\Models\Pen;
use App\Domain\Farm\Models\Room;
use App\Enums\LookupCategory;
use App\Filament\Resources\Pens\Pages\CreatePen;
use App\Filament\Resources\Pens\Pages\EditPen;
use App\Filament\Resources\Pens\Pages\ListPens;
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

class PenResource extends Resource
{
    protected static ?string $model = Pen::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'code';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(30)->unique(ignoreRecord: true)->helperText('Unique business identifier; stored in upper case.'),
            TextInput::make('name')->maxLength(255),
            Select::make('building_id')->label('Building')->relationship('building', 'name')->required()->preload()->searchable()->live()->afterStateUpdated(fn ($set) => $set('room_id', null)),
            Select::make('room_id')->label('Room')->options(fn ($get) => $get('building_id') ? Room::where('building_id', $get('building_id'))->orderBy('name')->pluck('name', 'id') : [])->searchable()->helperText('Optional. Only rooms of the chosen building are offered.'),
            Select::make('purpose_id')->label('Purpose')->relationship('purpose', 'name', modifyQueryUsing: fn ($query) => $query->where('category', LookupCategory::PenPurpose->value)->where('is_active', true)->orderBy('sort_order'))->required()->preload()->searchable(),
            TextInput::make('capacity')->numeric()->minValue(0)->maxValue(65535)->integer(),
            Textarea::make('notes'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->toggleable(),
                TextColumn::make('building.name')->label('Building')->sortable(),
                TextColumn::make('room.name')->label('Room')->placeholder('-'),
                TextColumn::make('purpose.name')->label('Purpose')->badge(),
                TextColumn::make('capacity')->numeric(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('building_id')->label('Building')->relationship('building', 'name'),
                SelectFilter::make('purpose_id')->label('Purpose')->relationship('purpose', 'name'),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
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
