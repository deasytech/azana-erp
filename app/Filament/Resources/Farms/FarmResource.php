<?php

namespace App\Filament\Resources\Farms;

use App\Domain\Farm\Models\Farm;
use App\Filament\Resources\Farms\Pages\CreateFarm;
use App\Filament\Resources\Farms\Pages\EditFarm;
use App\Filament\Resources\Farms\Pages\ListFarms;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class FarmResource extends MasterResource
{
    protected static ?string $model = Farm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

    protected static ?int $navigationSort = 10;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            TextInput::make('legal_name')->maxLength(255),
            Textarea::make('address'),
            TextInput::make('phone')->tel()->maxLength(40),
            TextInput::make('email')->email()->maxLength(255),
            TextInput::make('timezone')->required()->default('Africa/Lagos')->maxLength(64),
            TextInput::make('currency_code')->label('Currency (ISO code)')->required()->length(3)->default('NGN')->dehydrateStateUsing(fn ($state) => strtoupper($state)),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('currency_code')->label('Currency'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFarms::route('/'),
            'create' => CreateFarm::route('/create'),
            'edit' => EditFarm::route('/{record}/edit'),
        ];
    }
}
