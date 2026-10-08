<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Supplier\Models\Supplier;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\PurchaseOrders\RelationManagers\LinesRelationManager;
use App\Filament\Resources\PurchaseOrders\RelationManagers\ReceiptsRelationManager;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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

/** What the farm has ordered from a supplier, from approval through delivery to payment. */
class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationLabel = 'Purchase orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')->description('Who we are buying from and when.')->columns(2)->schema([
                Select::make('supplier_id')->label('Supplier')->required()->searchable()
                    ->options(fn () => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                DatePicker::make('ordered_on')->default(now())->maxDate(now())->required(),
                DatePicker::make('expected_on')->label('Expected delivery'),
                Hidden::make('purchase_request_id'),
                Textarea::make('notes'),
            ]),
            Section::make('Items')->description('What is being ordered and at what cost.')->schema([
                Repeater::make('lines')->label('Items')->hiddenLabel()->columnSpanFull()->minItems(1)->columns(3)->addActionLabel('Add item')
                    ->schema([
                        Select::make('inventory_item_id')->label('Item')->required()->searchable()->distinct()
                            ->options(fn () => InventoryItem::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($i) => [$i->id => "{$i->code} - {$i->name} ({$i->unit->code})"])->all()),
                        TextInput::make('quantity')->numeric()->minValue(0.001)->step(0.001)->required(),
                        MoneyInput::make('unit_cost_minor', 'Cost per unit')->required(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('supplier'))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('supplier.name')->label('Supplier')->searchable(),
                TextColumn::make('ordered_on')->date()->sortable(),
                TextColumn::make('expected_on')->date()->placeholder('-'),
                MoneyColumn::make('total_minor', 'Total'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        PurchaseOrderStatus::Approved, PurchaseOrderStatus::Received => 'success',
                        PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::PendingApproval => 'warning',
                        PurchaseOrderStatus::Rejected, PurchaseOrderStatus::Cancelled => 'danger', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(AnimalResource::enumOptions(PurchaseOrderStatus::cases())),
                SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class, ReceiptsRelationManager::class, InvoicesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
