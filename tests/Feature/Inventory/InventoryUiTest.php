<?php

use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Actions\RequestStockAdjustment;
use App\Domain\Inventory\Actions\StartStockCount;
use App\Domain\Inventory\Actions\TransferStock;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ApprovalStatus;
use App\Enums\InventoryCategory;
use App\Enums\InventoryTransactionType as T;
use App\Enums\StockCountStatus;
use App\Filament\Pages\StockAlerts;
use App\Filament\Pages\StockOverview;
use App\Filament\Resources\FeedConsumption\Pages\CreateFeedConsumption;
use App\Filament\Resources\InventoryBatches\InventoryBatchResource;
use App\Filament\Resources\InventoryBatches\Pages\ListInventoryBatches;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryLocations\InventoryLocationResource;
use App\Filament\Resources\InventoryTransactions\InventoryTransactionResource;
use App\Filament\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Filament\Resources\StockAdjustments\Pages\CreateStockAdjustment;
use App\Filament\Resources\StockAdjustments\Pages\ListStockAdjustments;
use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use App\Filament\Resources\StockCounts\Pages\CreateStockCount;
use App\Filament\Resources\StockCounts\Pages\ViewStockCount;
use App\Filament\Resources\StockCounts\RelationManagers\LinesRelationManager;
use App\Filament\Resources\StockCounts\StockCountResource;
use App\Filament\Widgets\StockByStoreChartWidget;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('renders every inventory page for the owner', function () {
    $this->actingAs(owner());
    receiveStock(stockItem('MAIZE', ['reorder_level' => '500']), '100', 35000);
    $count = app(StartStockCount::class)(store(), now()->startOfDay());

    foreach ([
        StockOverview::getUrl(), StockAlerts::getUrl(),
        InventoryItemResource::getUrl('index'), InventoryItemResource::getUrl('create'),
        InventoryLocationResource::getUrl('index'), InventoryLocationResource::getUrl('create'),
        InventoryTransactionResource::getUrl('index'), InventoryBatchResource::getUrl('index'),
        StockCountResource::getUrl('index'), StockCountResource::getUrl('create'), StockCountResource::getUrl('view', ['record' => $count]),
        StockAdjustmentResource::getUrl('index'), StockAdjustmentResource::getUrl('create'),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(StockOverview::getUrl())->assertSee('Item MAIZE');
});

it('shows stock with its value, filtered by item and store', function () {
    $this->actingAs(owner());
    receiveStock(stockItem('MAIZE'), '100', 35000);
    receiveStock(stockItem('SOYA'), '10', 90000, store('SILO'));

    Livewire::test(StockOverview::class)
        ->assertSee('Item MAIZE')->assertSee('Item SOYA')
        ->assertSee('NGN 44,000.00')   // 3,500,000 + 900,000 minor
        ->set('itemId', (string) stockItem('SOYA')->id)
        ->assertSee('NGN 9,000.00')->assertDontSee('NGN 35,000.00')   // only the soya row (the item list always names both)
        ->set('itemId', 'garbage')->assertSee('NGN 35,000.00');   // an invalid filter is ignored, never an error
});

it('receives, uses and transfers stock from the overview', function () {
    $this->actingAs(owner());
    $item = stockItem('MAIZE');
    $page = Livewire::test(StockOverview::class);
    $today = now()->toDateString();

    $page->callAction('receive', ['type' => 'opening', 'inventory_item_id' => $item->id, 'inventory_location_id' => store()->id, 'quantity' => 100, 'unit_cost_minor' => '350.50', 'occurred_on' => $today])
        ->assertNotified('Stock received');
    expect(InventoryTransaction::sole()->value_minor)->toBe(3505000);

    $page->callAction('issue', ['type' => 'consumption', 'inventory_item_id' => $item->id, 'inventory_location_id' => store()->id, 'quantity' => 30, 'occurred_on' => $today])
        ->assertNotified('Stock taken out');
    $page->callAction('transfer', ['inventory_item_id' => $item->id, 'from_id' => store()->id, 'to_id' => store('SILO')->id, 'quantity' => 20, 'occurred_on' => $today])
        ->assertNotified('Stock transferred');

    expect(app(GetStockLevels::class)->total($item->id))->toBe('70.000')
        ->and(app(GetStockLevels::class)(locationId: store('SILO')->id)->sole()->on_hand)->toBe('20.000');
});

it('shows rule violations from stock actions as notifications without saving', function () {
    $this->actingAs(owner());
    $item = stockItem('MAIZE');

    Livewire::test(StockOverview::class)
        ->callAction('issue', ['type' => 'consumption', 'inventory_item_id' => $item->id, 'inventory_location_id' => store()->id, 'quantity' => 5, 'occurred_on' => now()->toDateString()])
        ->assertNotified('Not saved');

    expect(InventoryTransaction::count())->toBe(0);
});

it('needs a batch number and expiry only for items that track them', function () {
    $this->actingAs(owner());
    $vaccine = stockItem('VAC', ['tracks_batches' => true, 'tracks_expiry' => true]);
    $base = ['type' => 'opening', 'inventory_item_id' => $vaccine->id, 'inventory_location_id' => store()->id, 'quantity' => 10, 'unit_cost_minor' => '100', 'occurred_on' => now()->toDateString()];

    Livewire::test(StockOverview::class)->callAction('receive', $base)->assertHasActionErrors(['batch_number', 'expiry_date']);
    expect(InventoryTransaction::count())->toBe(0);

    Livewire::test(StockOverview::class)
        ->callAction('receive', $base + ['batch_number' => 'LOT1', 'expiry_date' => now()->addYear()->toDateString()])->assertNotified('Stock received');
});

it('creates an item that links finished feed to a feed type', function () {
    $this->actingAs(owner());

    Livewire::test(CreateInventoryItem::class)
        ->fillForm(['code' => 'grower-meal', 'name' => 'Grower meal', 'category' => InventoryCategory::FinishedFeed->value, 'unit_id' => UnitOfMeasure::firstWhere('code', 'KG')->id, 'feed_type_id' => growerFeed()->id, 'tracks_expiry' => true])
        ->call('create')->assertHasNoFormErrors();

    $item = InventoryItem::firstWhere('code', 'GROWER-MEAL');
    expect($item->feed_type_id)->toBe(growerFeed()->id)->and($item->tracks_batches)->toBeTrue();

    Livewire::test(CreateInventoryItem::class)
        ->fillForm(['code' => 'other', 'name' => 'Other', 'category' => InventoryCategory::FinishedFeed->value, 'unit_id' => $item->unit_id, 'feed_type_id' => growerFeed()->id])
        ->call('create')->assertHasFormErrors(['feed_type_id']);
});

it('lists the ledger and reverses a line from it', function () {
    $this->actingAs(owner());
    $line = receiveStock(stockItem('MAIZE'), '10', 100);

    Livewire::test(ListInventoryTransactions::class)
        ->assertCanSeeTableRecords([$line])
        ->callAction(TestAction::make('reverse')->table($line), ['reason' => 'Wrong item'])
        ->assertNotified('Transaction reversed');

    expect(InventoryTransaction::count())->toBe(2)->and(app(GetStockLevels::class)())->toBeEmpty();

    Livewire::test(ListInventoryTransactions::class)->assertActionHidden(TestAction::make('reverse')->table($line));
});

it('reverses both sides of a transfer together, or neither', function () {
    $this->actingAs(owner());
    $item = stockItem('MAIZE');
    receiveStock($item, '100', 100, store('MAIN'));
    $legs = app(TransferStock::class)($item, store('MAIN'), store('SILO'), '40', now()->startOfDay());
    $out = $legs->first(fn ($l) => $l->type === T::TransferOut);
    $in = $legs->first(fn ($l) => $l->type === T::TransferIn);

    // The silo stock is used, so the transfer can no longer be undone: nothing at all is reversed.
    issueStock($item, '30', store('SILO'));
    Livewire::test(ListInventoryTransactions::class)
        ->callAction(TestAction::make('reverse')->table($out), ['reason' => 'Mistake'])->assertNotified('Not saved');
    expect(InventoryTransaction::whereNotNull('reverses_id')->count())->toBe(0);

    // A transfer nothing has touched is undone on both sides at once.
    $soya = stockItem('SOYA');
    receiveStock($soya, '50', 200, store('MAIN'));
    $legs = app(TransferStock::class)($soya, store('MAIN'), store('SILO'), '20', now()->startOfDay());
    $out = $legs->first(fn ($l) => $l->type === T::TransferOut);
    $in = $legs->first(fn ($l) => $l->type === T::TransferIn);

    Livewire::test(ListInventoryTransactions::class)
        ->callAction(TestAction::make('reverse')->table($out), ['reason' => 'Mistake'])->assertNotified('Transaction reversed');

    expect(InventoryTransaction::whereIn('reverses_id', [$out->id, $in->id])->count())->toBe(2)
        ->and(app(GetStockLevels::class)->total($soya->id))->toBe('50.000')
        ->and(app(GetStockLevels::class)(itemId: $soya->id, locationId: store('SILO')->id))->toBeEmpty();
    Livewire::test(ListInventoryTransactions::class)->assertActionHidden(TestAction::make('reverse')->table($in));
});

it('does not offer reversing a line that a goods receipt or feed record owns', function () {
    $this->actingAs(owner());
    $line = receiveStock(stockItem('MAIZE'), '10', 100, details: ['source_type' => 'goods_receipt', 'source_id' => 1]);

    Livewire::test(ListInventoryTransactions::class)->assertActionHidden(TestAction::make('reverse')->table($line));
});

it('walks a count from start to approval and posts the variance', function () {
    $counter = userWithRole('Store Officer');
    $manager = userWithRole('Farm Manager');
    $maize = stockItem('MAIZE');
    receiveStock($maize, '100', 100);

    $this->actingAs($counter);
    Livewire::test(CreateStockCount::class)->fillForm(['inventory_location_id' => store()->id, 'counted_on' => now()->toDateString()])->call('create')->assertHasNoFormErrors();
    $count = StockCount::sole();

    Livewire::test(LinesRelationManager::class, ['ownerRecord' => $count, 'pageClass' => ViewStockCount::class])
        ->callAction(TestAction::make('count')->table($count->lines->first()), ['counted_quantity' => 90, 'reason' => 'Spillage'])
        ->assertNotified('Count saved');

    Livewire::test(ViewStockCount::class, ['record' => $count->getRouteKey()])
        ->assertActionVisible('submit')->assertActionHidden('approve')
        ->callAction('submit')->assertNotified('Count submitted')
        ->assertActionHidden('approve');   // the counter has no approval right

    expect($count->fresh()->status)->toBe(StockCountStatus::Submitted);

    $this->actingAs($manager);
    Livewire::test(ViewStockCount::class, ['record' => $count->getRouteKey()])
        ->assertActionVisible('approve')->callAction('approve')->assertNotified('Count approved and stock adjusted');

    expect($count->fresh()->status)->toBe(StockCountStatus::Approved)
        ->and(app(GetStockLevels::class)->total($maize->id))->toBe('90.000')
        ->and(InventoryTransaction::where('type', T::Adjustment)->sole()->user_id)->toBe($manager->id);
});

it('explains why an incomplete count cannot be submitted', function () {
    $this->actingAs(owner());
    receiveStock(stockItem('MAIZE'), '10', 100);
    $count = app(StartStockCount::class)(store(), now()->startOfDay());

    Livewire::test(ViewStockCount::class, ['record' => $count->getRouteKey()])->callAction('submit')->assertNotified('Not saved');
    expect($count->fresh()->status)->toBe(StockCountStatus::Draft);
});

it('adds stock found during a count', function () {
    $this->actingAs(owner());
    $count = app(StartStockCount::class)(store(), now()->startOfDay());

    Livewire::test(LinesRelationManager::class, ['ownerRecord' => $count, 'pageClass' => ViewStockCount::class])
        ->callAction(TestAction::make('found')->table(), ['inventory_item_id' => stockItem('SALT')->id, 'counted_quantity' => 5, 'reason' => 'Behind pallets'])
        ->assertNotified('Count saved');

    expect($count->lines()->sole()->variance_quantity)->toBe('5.000');
});

it('requests and decides adjustments from the list', function () {
    $clerk = userWithRole('Store Officer');
    $manager = userWithRole('Farm Manager');
    $maize = stockItem('MAIZE');
    receiveStock($maize, '100', 100);

    $this->actingAs($clerk);
    Livewire::test(CreateStockAdjustment::class)
        ->fillForm(['inventory_item_id' => $maize->id, 'inventory_location_id' => store()->id, 'quantity' => '-8', 'reason' => 'Rodents'])
        ->call('create')->assertHasNoFormErrors();
    $adjustment = StockAdjustment::sole();

    Livewire::test(ListStockAdjustments::class)->assertActionHidden(TestAction::make('approve')->table($adjustment));

    $this->actingAs($manager);
    Livewire::test(ListStockAdjustments::class)
        ->callAction(TestAction::make('approve')->table($adjustment), ['notes' => 'ok'])->assertNotified('Adjustment approved');

    expect($adjustment->fresh()->status)->toBe(ApprovalStatus::Approved)->and(app(GetStockLevels::class)->total($maize->id))->toBe('92.000');

    $second = app(RequestStockAdjustment::class)($maize, store(), null, '1', 'Found', $clerk);
    Livewire::test(ListStockAdjustments::class)
        ->callAction(TestAction::make('reject')->table($second), ['reason' => 'No'])->assertNotified('Adjustment rejected');
    expect($second->fresh()->status)->toBe(ApprovalStatus::Rejected);

    Livewire::test(CreateStockAdjustment::class)
        ->fillForm(['inventory_item_id' => $maize->id, 'inventory_location_id' => store()->id, 'quantity' => '0', 'reason' => 'x'])
        ->call('create')->assertHasFormErrors(['quantity']);

    // A batch-tracked item must name its batch, and the form says so before anything is saved.
    $vaccine = stockItem('VAC', ['tracks_batches' => true]);
    Livewire::test(CreateStockAdjustment::class)
        ->fillForm(['inventory_item_id' => $vaccine->id, 'inventory_location_id' => store()->id, 'quantity' => '-1', 'reason' => 'Broken'])
        ->call('create')->assertHasFormErrors(['inventory_batch_id' => 'required']);
});

it('blocks and unblocks a batch', function () {
    $this->actingAs(owner());
    $vaccine = stockItem('VAC', ['tracks_batches' => true]);
    receiveStock($vaccine, '10', 100, details: ['batch_number' => 'LOT1']);
    $batch = InventoryBatch::sole();

    Livewire::test(ListInventoryBatches::class)->assertCanSeeTableRecords([$batch])
        ->callAction(TestAction::make('toggle')->table($batch));

    expect($batch->fresh()->is_active)->toBeFalse();
    expect(fn () => issueStock($vaccine, '1', details: ['batch' => $batch->fresh()]))->toThrow(DomainException::class, 'not active');
});

it('shows reorder and expiry alerts', function () {
    $this->actingAs(owner());
    receiveStock(stockItem('MAIZE', ['reorder_level' => '500', 'reorder_quantity' => '1000']), '100', 100);
    receiveStock(stockItem('VAC', ['tracks_expiry' => true]), '5', 100, daysAgo: 1, details: ['batch_number' => 'SOON', 'expiry_date' => now()->addDays(10)->toDateString()]);

    Livewire::test(StockAlerts::class)->assertSee('Item MAIZE')->assertSee('Suggested order: 1000.000')->assertSee('Batch SOON');
});

it('can take recorded feed from a store', function () {
    $this->actingAs(owner());
    $meal = stockItem('GROWER-MEAL', ['feed_type_id' => growerFeed()->id]);
    receiveStock($meal, '500', 40000, store('FEED'));
    $batch = openBatch();

    Livewire::test(CreateFeedConsumption::class)
        ->fillForm(['target' => 'batch', 'production_batch_id' => $batch->id, 'feed_type_id' => growerFeed()->id, 'consumed_on' => now()->toDateString(), 'quantity_kg' => 50, 'inventory_location_id' => store('FEED')->id])
        ->call('create')->assertHasNoFormErrors();

    expect(app(GetStockLevels::class)->total($meal->id))->toBe('450.000')
        ->and($batch->feedRecords()->sole()->cost_minor)->toBe(2000000);
});

it('keeps stock screens away from people without stock rights', function () {
    $this->actingAs(userWithRole('Veterinarian'));

    foreach ([StockOverview::getUrl(), InventoryTransactionResource::getUrl('index'), StockCountResource::getUrl('index'), StockAdjustmentResource::getUrl('index')] as $url) {
        $this->get($url)->assertForbidden();
    }

    $this->actingAs(farmWorker());
    $this->get(StockOverview::getUrl())->assertOk();
    Livewire::test(StockOverview::class)->assertActionVisible('receive');
    $this->get(StockAdjustmentResource::getUrl('index'))->assertOk();
});

it('draws the value of stock by store and keeps the chart with the filters', function () {
    $this->actingAs(owner());
    receiveStock(stockItem('MAIZE'), '100', 35000);
    receiveStock(stockItem('SOYA'), '10', 90000, store('SILO'));

    // Chart widgets load lazily, so the page carries the component and its heading arrives with the first widget request.
    $this->get(StockOverview::getUrl())->assertOk()->assertSeeLivewire(StockByStoreChartWidget::class);
    Livewire::test(StockByStoreChartWidget::class)->assertSee('Stock value by store')->assertSee(store('SILO')->name);
    Livewire::test(StockByStoreChartWidget::class, ['location' => store('SILO')->id])->assertSee('Stock value by item in this store');

    Livewire::test(StockOverview::class)->set('locationId', (string) store('SILO')->id)
        ->assertDispatched('stock-overview-filter-changed', item: null, location: store('SILO')->id);
});
