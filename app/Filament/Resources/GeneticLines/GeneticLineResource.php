<?php

namespace App\Filament\Resources\GeneticLines;

use App\Domain\Farm\Models\GeneticLine;
use App\Filament\Resources\GeneticLines\Pages\CreateGeneticLine;
use App\Filament\Resources\GeneticLines\Pages\EditGeneticLine;
use App\Filament\Resources\GeneticLines\Pages\ListGeneticLines;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class GeneticLineResource extends MasterResource
{
    protected static ?string $model = GeneticLine::class;

    protected static ?string $codePrefix = 'GL';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 20;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('breed_id')->label('Breed')->relationship('breed', 'name')->preload()->searchable(),
            Textarea::make('description'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('breed.name')->label('Breed')->placeholder('-'),
        ];
    }

    protected static function filters(): array
    {
        return [
            SelectFilter::make('breed_id')->label('Breed')->relationship('breed', 'name'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeneticLines::route('/'),
            'create' => CreateGeneticLine::route('/create'),
            'edit' => EditGeneticLine::route('/{record}/edit'),
        ];
    }
}
