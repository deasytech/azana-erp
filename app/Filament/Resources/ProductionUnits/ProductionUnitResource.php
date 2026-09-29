<?php

namespace App\Filament\Resources\ProductionUnits;

use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\ProductionUnit;
use App\Enums\LookupCategory;
use App\Filament\Resources\ProductionUnits\Pages\CreateProductionUnit;
use App\Filament\Resources\ProductionUnits\Pages\EditProductionUnit;
use App\Filament\Resources\ProductionUnits\Pages\ListProductionUnits;
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

class ProductionUnitResource extends Resource
{
    protected static ?string $model = ProductionUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Farm structure';

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
            Select::make('farm_id')->label('Farm')->relationship('farm', 'name')->required()->default(fn () => Farm::orderBy('id')->value('id')),
            Select::make('type_id')->label('Type')->relationship('type', 'name', modifyQueryUsing: fn ($query) => $query->where('category', LookupCategory::ProductionUnitType->value)->where('is_active', true)->orderBy('sort_order'))->required()->preload()->searchable(),
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
                TextColumn::make('type.name')->label('Type')->badge(),
                TextColumn::make('buildings_count')->counts('buildings')->label('Buildings'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type_id')->label('Type')->relationship('type', 'name'),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionUnits::route('/'),
            'create' => CreateProductionUnit::route('/create'),
            'edit' => EditProductionUnit::route('/{record}/edit'),
        ];
    }
}
