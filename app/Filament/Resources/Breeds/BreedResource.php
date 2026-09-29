<?php

namespace App\Filament\Resources\Breeds;

use App\Domain\Farm\Models\Breed;
use App\Filament\Resources\Breeds\Pages\CreateBreed;
use App\Filament\Resources\Breeds\Pages\EditBreed;
use App\Filament\Resources\Breeds\Pages\ListBreeds;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class BreedResource extends Resource
{
    protected static ?string $model = Breed::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 10;

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
            TextInput::make('species')->required()->default('pig')->maxLength(30),
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
                TextColumn::make('species'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
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
