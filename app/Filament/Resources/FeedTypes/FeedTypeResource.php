<?php

namespace App\Filament\Resources\FeedTypes;

use App\Domain\Feed\Models\FeedType;
use App\Filament\Resources\FeedTypes\Pages\CreateFeedType;
use App\Filament\Resources\FeedTypes\Pages\EditFeedType;
use App\Filament\Resources\FeedTypes\Pages\ListFeedTypes;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class FeedTypeResource extends MasterResource
{
    protected static ?string $model = FeedType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?int $navigationSort = 50;

    protected static function fields(): array
    {
        return [static::nameField(), Textarea::make('description')];
    }

    protected static function columns(): array
    {
        return [TextColumn::make('name')->searchable(), TextColumn::make('description')->limit(60)->placeholder('-')];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedTypes::route('/'),
            'create' => CreateFeedType::route('/create'),
            'edit' => EditFeedType::route('/{record}/edit'),
        ];
    }
}
