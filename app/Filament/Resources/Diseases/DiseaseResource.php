<?php

namespace App\Filament\Resources\Diseases;

use App\Domain\Health\Models\Disease;
use App\Filament\Resources\Diseases\Pages\CreateDisease;
use App\Filament\Resources\Diseases\Pages\EditDisease;
use App\Filament\Resources\Diseases\Pages\ListDiseases;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class DiseaseResource extends MasterResource
{
    protected static ?string $model = Disease::class;

    protected static ?string $codePrefix = 'DIS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 60;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Textarea::make('description'),
            Toggle::make('is_reportable')->label('Notifiable / reportable disease'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            IconColumn::make('is_reportable')->label('Reportable')->boolean(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiseases::route('/'),
            'create' => CreateDisease::route('/create'),
            'edit' => EditDisease::route('/{record}/edit'),
        ];
    }
}
