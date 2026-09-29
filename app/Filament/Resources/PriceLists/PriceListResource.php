<?php

namespace App\Filament\Resources\PriceLists;

use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\PriceList;
use App\Enums\LookupCategory;
use App\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Filament\Resources\PriceLists\RelationManagers\ItemsRelationManager;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

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
            TextInput::make('code')->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('farm_id')->label('Farm')->relationship('farm', 'name')->required()
                ->default(fn () => Farm::orderBy('id')->value('id'))
                ->live()
                ->afterStateUpdated(fn ($state, $set) => $set('currency_code', Farm::find($state)?->currency_code)),
            Select::make('category_id')->label('Category')
                ->relationship('category', 'name', modifyQueryUsing: fn ($query) => $query->where('category', LookupCategory::PriceCategory->value)->where('is_active', true)->orderBy('sort_order'))
                ->required()->preload(),
            TextInput::make('currency_code')->label('Currency (ISO code)')->required()->length(3)
                ->default(fn () => Farm::orderBy('id')->value('currency_code') ?? 'NGN')
                ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
            DatePicker::make('valid_from'),
            DatePicker::make('valid_to')->afterOrEqual('valid_from'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('category.name')->badge(),
                TextColumn::make('currency_code')->label('Currency'),
                TextColumn::make('items_count')->counts('items')->label('Items'),
                TextColumn::make('valid_from')->date(),
                TextColumn::make('valid_to')->date(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
