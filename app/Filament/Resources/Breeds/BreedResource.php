<?php

namespace App\Filament\Resources\Breeds;

use App\Domain\Farm\Models\Breed;
use App\Filament\Resources\Breeds\Pages\CreateBreed;
use App\Filament\Resources\Breeds\Pages\EditBreed;
use App\Filament\Resources\Breeds\Pages\ListBreeds;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class BreedResource extends MasterResource
{
    protected static ?string $model = Breed::class;

    protected static ?string $codePrefix = 'BRD';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 10;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            TextInput::make('species')->required()->default('pig')->maxLength(30),
            Textarea::make('description'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('species'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBreeds::route('/'),
            'create' => CreateBreed::route('/create'),
            'edit' => EditBreed::route('/{record}/edit'),
        ];
    }
}
