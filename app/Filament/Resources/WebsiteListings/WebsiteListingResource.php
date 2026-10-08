<?php

namespace App\Filament\Resources\WebsiteListings;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Website\Models\Listing;
use App\Enums\InventoryCategory;
use App\Enums\ListingKind;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\WebsiteListings\Pages\CreateWebsiteListing;
use App\Filament\Resources\WebsiteListings\Pages\EditWebsiteListing;
use App\Filament\Resources\WebsiteListings\Pages\ListWebsiteListings;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** What the public website offers. Prices and availability are read from the price lists and the stock ledger, not typed here. */
class WebsiteListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static ?string $modelLabel = 'listing';

    protected static ?string $navigationLabel = 'Listings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What is offered')->description('The product and the words visitors read.')->columnSpanFull()->columns(2)->schema([
                Select::make('kind')->options(AnimalResource::enumOptions(ListingKind::cases()))->required()->live()
                    ->afterStateUpdated(fn ($set) => $set('inventory_item_id', null)),
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('slug')->label('Web address')->maxLength(120)->helperText('Made from the title when left empty, for example "duroc-semen".'),
                TextInput::make('summary')->required()->maxLength(300)->columnSpanFull(),
                Textarea::make('description')->rows(6)->columnSpanFull()->helperText('Blank lines start a new paragraph.'),
            ]),
            Section::make('Stock and price')->description('Where the price and availability come from.')->columnSpanFull()->columns(2)->schema([
                Select::make('inventory_item_id')->label('Stock item')->searchable()->placeholder('None')
                    ->visible(fn ($get) => in_array($get('kind'), [ListingKind::Semen->value, ListingKind::Meat->value], true))
                    ->options(fn ($get) => InventoryItem::where('is_active', true)
                        ->where('category', $get('kind') === ListingKind::Semen->value ? InventoryCategory::Semen : InventoryCategory::Meat)
                        ->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Links the listing to its price list entry and to the stock it is sold from.'),
                TextInput::make('price_unit')->label('Price is quoted')->maxLength(30)->placeholder('per dose')->helperText('Words shown after the price.'),
                Toggle::make('show_price')->label('Show the price')->helperText('The price is the one on the active price list for the stock item.'),
            ]),
            Section::make('Publishing')->description('Order on the site and whether it is live.')->columnSpanFull()->columns(2)->schema([
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->maxValue(65000)->default(100)->helperText('Smaller numbers come first.'),
                Toggle::make('is_published')->label('Published on the website'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('item.name')->label('Stock item')->placeholder('-'),
                IconColumn::make('show_price')->label('Price shown')->boolean(),
                IconColumn::make('is_published')->label('Published')->boolean(),
            ])
            ->filters([
                SelectFilter::make('kind')->options(AnimalResource::enumOptions(ListingKind::cases())),
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsiteListings::route('/'),
            'create' => CreateWebsiteListing::route('/create'),
            'edit' => EditWebsiteListing::route('/{record}/edit'),
        ];
    }
}
