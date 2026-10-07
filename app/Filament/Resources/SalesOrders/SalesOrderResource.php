<?php

namespace App\Filament\Resources\SalesOrders;

use App\Domain\Meat\Actions\GetMeatStock;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\BatchStatus;
use App\Enums\SalesLineKind;
use App\Enums\SalesOrderStatus;
use App\Enums\SemenBatchStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\RelationManagers\LinesRelationManager;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\StockForms;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Semen and pig orders: drafted, confirmed (stock reserved, credit checked) and dispatched (invoiced). */
class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationLabel = 'Orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        $is = fn (SalesLineKind $kind) => fn ($get) => $get('kind') === $kind->value;

        return $schema->columns(2)->components([
            Select::make('customer_id')->label('Customer')->required()->searchable()
                ->options(fn () => Customer::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => "{$c->code} - {$c->name}"])->all()),
            DatePicker::make('ordered_on')->default(now())->maxDate(now())->required(),
            Textarea::make('notes')->columnSpanFull(),
            Repeater::make('lines')->label('Lines')->columnSpanFull()->minItems(1)->columns(3)->addActionLabel('Add line')
                ->schema([
                    Select::make('kind')->label('Sells')->options(collect(SalesLineKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])->all())->required()->live()->default(SalesLineKind::Semen->value),
                    Select::make('semen_batch_id')->label('Semen batch')->searchable()->visible($is(SalesLineKind::Semen))->required($is(SalesLineKind::Semen))
                        ->options(fn () => SemenBatch::with(['boar', 'inventoryBatch'])->where('status', SemenBatchStatus::Released)->whereDate('expiry_date', '>=', now())->orderBy('expiry_date')->get()
                            ->filter->isSellable()->mapWithKeys(fn (SemenBatch $b) => [$b->id => "{$b->number} ({$b->boar->animal_number}, expires {$b->expiry_date->format('d M')})"])->all()),
                    StockForms::store()->visible(fn ($get) => in_array($get('kind'), [SalesLineKind::Semen->value, SalesLineKind::Meat->value], true))->required(fn ($get) => in_array($get('kind'), [SalesLineKind::Semen->value, SalesLineKind::Meat->value], true))
                        ->label(fn ($get) => $get('kind') === SalesLineKind::Meat->value ? 'Take meat from (cold room)' : 'Take doses from'),
                    Select::make('meat_product_id')->label('Meat product')->searchable()->live()->visible($is(SalesLineKind::Meat))->required($is(SalesLineKind::Meat))
                        ->options(fn () => MeatProduct::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                    Select::make('meat_production_line_id')->label('Specific batch (optional)')->searchable()->visible($is(SalesLineKind::Meat))
                        ->helperText('Leave empty to pick the batches closest to their use-by date.')
                        ->options(fn ($get) => collect(app(GetMeatStock::class)())->where('product_id', (int) $get('meat_product_id'))->where('expired', false)
                            ->mapWithKeys(fn (array $r) => [$r['line_id'] => "{$r['batch']} - {$r['kg']} kg, use by {$r['use_by']->format('d M')}"])->all()),
                    AnimalPicker::any()->visible($is(SalesLineKind::PigAnimal))->required($is(SalesLineKind::PigAnimal))->label('Pig'),
                    Select::make('production_batch_id')->label('Batch')->searchable()->visible($is(SalesLineKind::PigBatch))->required($is(SalesLineKind::PigBatch))
                        ->options(fn () => ProductionBatch::where('status', BatchStatus::Active->value)->orderBy('code')->get()->mapWithKeys(fn ($b) => [$b->id => "{$b->code} - {$b->name}"])->all()),
                    TextInput::make('heads')->label('Pigs sold')->numeric()->integer()->minValue(1)->visible($is(SalesLineKind::PigBatch))->required($is(SalesLineKind::PigBatch)),
                    Select::make('unit')->options(['head' => 'Per head', 'kg' => 'Per kg live weight'])->default('head')->live()->visible(fn ($get) => ! in_array($get('kind'), [SalesLineKind::Semen->value, SalesLineKind::Meat->value], true)),
                    TextInput::make('quantity')->label(fn ($get) => match ($get('kind')) {
                        SalesLineKind::Semen->value => 'Doses', SalesLineKind::Meat->value => 'Weight (kg)', default => 'Live weight (kg)',
                    })->numeric()->minValue(0.001)->step(0.001)
                        ->visible(fn ($get) => in_array($get('kind'), [SalesLineKind::Semen->value, SalesLineKind::Meat->value], true) || $get('unit') === 'kg')
                        ->required(fn ($get) => in_array($get('kind'), [SalesLineKind::Semen->value, SalesLineKind::Meat->value], true) || $get('unit') === 'kg'),
                    MoneyInput::make('unit_price_minor', 'Price per dose / head / kg')->helperText('Leave empty for semen and meat to use the price list.'),
                    TextInput::make('discount_percent')->label('Discount (%)')->numeric()->minValue(0)->maxValue(100)->step(0.01)->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('ordered_on')->date()->sortable(),
                MoneyColumn::make('total_minor', 'Total'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        SalesOrderStatus::Dispatched => 'success', SalesOrderStatus::Confirmed => 'warning', SalesOrderStatus::Cancelled => 'danger', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(AnimalResource::enumOptions(SalesOrderStatus::cases())),
                SelectFilter::make('customer_id')->label('Customer')->relationship('customer', 'name')->searchable()->preload(),
            ])
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
            'index' => ListSalesOrders::route('/'),
            'create' => CreateSalesOrder::route('/create'),
            'view' => ViewSalesOrder::route('/{record}'),
        ];
    }
}
