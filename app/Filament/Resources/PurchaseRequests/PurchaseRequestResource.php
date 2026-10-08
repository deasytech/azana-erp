<?php

namespace App\Filament\Resources\PurchaseRequests;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Procurement\Models\PurchaseRequest;
use App\Enums\PurchaseRequestStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\PurchaseRequests\Pages\CreatePurchaseRequest;
use App\Filament\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\RelationManagers\LinesRelationManager;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** What the farm asks to buy, before any supplier is chosen. */
class PurchaseRequestResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    protected static ?string $navigationLabel = 'Purchase requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->description('When it is needed and why.')->columnSpanFull()->columns(2)->schema([
                DatePicker::make('needed_by')->minDate(now()),
                Textarea::make('notes'),
            ]),
            Section::make('Items')->description('What is needed and an estimate of the cost.')->columnSpanFull()->schema([
                Repeater::make('lines')->label('Items')->hiddenLabel()->columnSpanFull()->minItems(1)->columns(4)->addActionLabel('Add item')
                    ->schema([
                        Select::make('inventory_item_id')->label('Item')->required()->searchable()->distinct()
                            ->options(fn () => InventoryItem::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($i) => [$i->id => "{$i->code} - {$i->name} ({$i->unit->code})"])->all()),
                        TextInput::make('quantity')->numeric()->minValue(0.001)->step(0.001)->required(),
                        MoneyInput::make('estimated_unit_cost_minor', 'Estimated cost per unit'),
                        TextInput::make('notes')->maxLength(255),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('requestedBy')->withCount('lines'))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('lines_count')->label('Items'),
                TextColumn::make('needed_by')->date()->placeholder('-')->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        PurchaseRequestStatus::Approved, PurchaseRequestStatus::Ordered => 'success',
                        PurchaseRequestStatus::Rejected => 'danger', PurchaseRequestStatus::Submitted => 'warning', default => 'gray',
                    }),
                TextColumn::make('requestedBy.name')->label('Requested by')->placeholder('-'),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(PurchaseRequestStatus::cases()))])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseRequests::route('/'),
            'create' => CreatePurchaseRequest::route('/create'),
            'view' => ViewPurchaseRequest::route('/{record}'),
        ];
    }
}
