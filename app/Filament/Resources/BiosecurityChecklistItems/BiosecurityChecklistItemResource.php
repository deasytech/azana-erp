<?php

namespace App\Filament\Resources\BiosecurityChecklistItems;

use App\Domain\Biosecurity\Models\BiosecurityChecklistItem;
use App\Filament\Resources\BiosecurityChecklistItems\Pages\CreateBiosecurityChecklistItem;
use App\Filament\Resources\BiosecurityChecklistItems\Pages\EditBiosecurityChecklistItem;
use App\Filament\Resources\BiosecurityChecklistItems\Pages\ListBiosecurityChecklistItems;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class BiosecurityChecklistItemResource extends MasterResource
{
    protected static ?string $model = BiosecurityChecklistItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Biosecurity';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'description';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'description'];
    }

    protected static function fields(): array
    {
        return [
            TextInput::make('description')->required()->maxLength(255),
            TextInput::make('area')->maxLength(60)->helperText('Optional grouping, e.g. Gate, Feed store, Farrowing house.'),
            TextInput::make('sort_order')->numeric()->integer()->minValue(0)->default(0),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('description')->searchable()->wrap(),
            TextColumn::make('area')->placeholder('-'),
            TextColumn::make('sort_order')->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBiosecurityChecklistItems::route('/'),
            'create' => CreateBiosecurityChecklistItem::route('/create'),
            'edit' => EditBiosecurityChecklistItem::route('/{record}/edit'),
        ];
    }
}
