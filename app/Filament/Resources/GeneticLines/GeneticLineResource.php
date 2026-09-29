<?php

namespace App\Filament\Resources\GeneticLines;

use App\Domain\Farm\Models\GeneticLine;
use App\Filament\Resources\GeneticLines\Pages\CreateGeneticLine;
use App\Filament\Resources\GeneticLines\Pages\EditGeneticLine;
use App\Filament\Resources\GeneticLines\Pages\ListGeneticLines;
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

class GeneticLineResource extends Resource
{
    protected static ?string $model = GeneticLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 20;

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
            Select::make('breed_id')->label('Breed')->relationship('breed', 'name')->preload()->searchable(),
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
                TextColumn::make('breed.name')->label('Breed')->placeholder('-'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('breed_id')->label('Breed')->relationship('breed', 'name'),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
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
