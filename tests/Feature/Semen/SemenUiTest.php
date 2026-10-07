<?php

use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\PriceList;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Semen\Actions\ManageSemenBatch;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Semen\Models\SemenBoar;
use App\Enums\LookupCategory;
use App\Enums\SemenBatchStatus as Status;
use App\Enums\SemenBoarStatus;
use App\Filament\Pages\SemenProduction;
use App\Filament\Pages\SemenStock;
use App\Filament\Resources\BreedingServices\Pages\CreateBreedingService;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\SemenBatches\Pages\CreateSemenBatch;
use App\Filament\Resources\SemenBatches\Pages\ViewSemenBatch;
use App\Filament\Resources\SemenBatches\RelationManagers\QcRecordsRelationManager;
use App\Filament\Resources\SemenBatches\SemenBatchResource;
use App\Filament\Resources\SemenBoars\Pages\CreateSemenBoar;
use App\Filament\Resources\SemenBoars\Pages\EditSemenBoar;
use App\Filament\Resources\SemenBoars\SemenBoarResource;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function semenBatchPage(SemenBatch $batch)
{
    return Livewire::test(ViewSemenBatch::class, ['record' => $batch->getRouteKey()]);
}

it('renders every semen page for the owner', function () {
    $this->actingAs(owner());
    $batch = releasedSemen();

    foreach ([
        SemenStock::getUrl(), SemenProduction::getUrl(),
        SemenBoarResource::getUrl('index'), SemenBoarResource::getUrl('create'), SemenBoarResource::getUrl('edit', ['record' => SemenBoar::first()]),
        SemenBatchResource::getUrl('index'), SemenBatchResource::getUrl('create'), SemenBatchResource::getUrl('view', ['record' => $batch]),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(SemenBatchResource::getUrl('index'))->assertSee($batch->number);
    $this->get(SemenStock::getUrl())->assertSee($batch->number)->assertSee('Duroc');
});

it('adds a boar to the programme and edits its limits', function () {
    $this->actingAs(owner());
    $boar = register(['sex' => 'male', 'category_id' => categoryId('boar')]);
    $sow = register();

    Livewire::test(CreateSemenBoar::class)
        ->fillForm(['animal_id' => $boar->id, 'status' => 'active', 'min_interval_days' => 3, 'target_doses_per_week' => 50])
        ->call('create')->assertHasNoFormErrors();

    $programme = SemenBoar::sole();
    expect($programme->animal_id)->toBe($boar->id)->and($programme->min_interval_days)->toBe(3);

    // Only boars not yet in the programme are offered, and a sow never is.
    Livewire::test(CreateSemenBoar::class)->fillForm(['animal_id' => $boar->id, 'status' => 'active'])->call('create')->assertHasFormErrors(['animal_id']);
    Livewire::test(CreateSemenBoar::class)->fillForm(['animal_id' => $sow->id, 'status' => 'active'])->call('create')->assertHasFormErrors(['animal_id']);

    Livewire::test(EditSemenBoar::class, ['record' => $programme->getRouteKey()])->fillForm(['target_doses_per_week' => 100001])->call('save')->assertHasFormErrors(['target_doses_per_week']);

    Livewire::test(EditSemenBoar::class, ['record' => $programme->getRouteKey()])->fillForm(['status' => 'resting', 'target_doses_per_week' => 70])->call('save')->assertHasNoFormErrors();
    expect($programme->fresh()->status)->toBe(SemenBoarStatus::Resting)->and($programme->fresh()->target_doses_per_week)->toBe(70);
});

it('records a collection through the form and opens its batch', function () {
    $this->actingAs(owner());
    $boar = semenBoar();

    Livewire::test(CreateSemenBatch::class)
        ->fillForm(['animal_id' => $boar->id, 'collected_at' => now()->subHour()->toDateTimeString(), 'volume_ml' => 250, 'ph' => 7.2, 'technician_name' => 'Ada'])
        ->call('create')->assertHasNoFormErrors();

    $batch = SemenBatch::sole();
    expect($batch->status)->toBe(Status::PendingQc)->and($batch->collection->technician_name)->toBe('Ada')->and($batch->collection->ph)->toBe('7.2');

    // The boar must rest before the next collection: a rule violation is a notification, not an error page.
    Livewire::test(CreateSemenBatch::class)
        ->fillForm(['animal_id' => $boar->id, 'collected_at' => now()->toDateTimeString(), 'volume_ml' => 200])
        ->call('create')->assertNotified('Not saved');
    expect(SemenBatch::count())->toBe(1);
});

it('walks a batch from QC to release and shows it in stock', function () {
    $analyst = userWithRole('Semen Laboratory Manager');
    $manager = userWithRole('Farm Manager');
    $batch = collectSemen(semenBoar());

    $this->actingAs($analyst);
    semenBatchPage($batch)->assertActionVisible('qc')->assertActionHidden('process')->assertActionHidden('release')
        ->callAction('qc', ['motility' => 80, 'concentration' => 300, 'abnormal' => 10])->assertNotified('QC recorded');
    expect($batch->fresh()->status)->toBe(Status::Passed);

    semenBatchPage($batch)->assertActionHidden('qc')->assertActionHidden('release')   // not processed yet
        ->callAction('process', ['doses' => 24, 'dose_volume' => 80, 'diluent' => 'BTS'])->assertNotified('Doses recorded');

    // The analyst who did the QC cannot also release it.
    semenBatchPage($batch)->assertActionVisible('release')
        ->callAction('release', ['inventory_location_id' => store('SEMEN')->id])->assertNotified('Not saved');
    expect($batch->fresh()->status)->toBe(Status::Passed);

    $this->actingAs($manager);
    semenBatchPage($batch)->callAction('release', ['inventory_location_id' => store('SEMEN')->id])->assertNotified('Batch released into stock');

    expect($batch->fresh()->status)->toBe(Status::Released)->and(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'SEMEN-DUR')->id))->toBe('24.000');
    Livewire::test(SemenStock::class)->assertSee($batch->number)->assertSee('24.000');
    Livewire::test(QcRecordsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewSemenBatch::class])->assertSee('80.00');
});

it('explains a failed QC and offers no way to sell the batch', function () {
    $this->actingAs(owner());
    $batch = collectSemen(semenBoar());

    semenBatchPage($batch)->callAction('qc', ['motility' => 40, 'concentration' => 300, 'abnormal' => 10])->assertNotified('QC recorded');

    expect($batch->fresh()->status)->toBe(Status::Failed);
    semenBatchPage($batch)->assertActionHidden('process')->assertActionHidden('release')->assertActionHidden('qc')->assertActionVisible('destroy');
    Livewire::test(QcRecordsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewSemenBatch::class])->assertSee('Motility 40% is below 70%');
});

it('limits the doses to what the ejaculate can yield and says so', function () {
    $this->actingAs(owner());
    $batch = passSemenQc(collectSemen(semenBoar()));

    semenBatchPage($batch)->callAction('process', ['doses' => 25, 'dose_volume' => 80])->assertNotified('Not saved');
    expect($batch->fresh()->doses_produced)->toBeNull();
});

it('quarantines, clears and destroys a released batch', function () {
    $this->actingAs(owner());
    $batch = releasedSemen();

    semenBatchPage($batch)->callAction('quarantine', ['reason' => 'Recall'])->assertNotified('Batch quarantined');
    expect($batch->fresh()->isSellable())->toBeFalse();
    semenBatchPage($batch)->assertActionHidden('quarantine')->assertActionVisible('clear')->callAction('clear')->assertNotified('Quarantine cleared');
    expect($batch->fresh()->isSellable())->toBeTrue();

    semenBatchPage($batch)->callAction('destroy', ['reason' => 'Cold chain failed'])->assertNotified('Batch destroyed');
    expect($batch->fresh()->status)->toBe(Status::Destroyed)->and(app(GetStockLevels::class)())->toBeEmpty();
    semenBatchPage($batch)->assertActionHidden('destroy')->assertSee('Cold chain failed');
});

it('hides release and destroy from people who cannot approve', function () {
    $batch = processedSemen();

    $this->actingAs(farmWorker());
    semenBatchPage($batch)->assertActionHidden('release')->assertActionHidden('destroy')->assertActionHidden('clear');

    $this->actingAs(userWithRole('Semen Laboratory Manager'));
    semenBatchPage($batch)->assertActionVisible('release')->assertActionVisible('destroy');
});

it('shows the production report and survives a tampered period', function () {
    $this->actingAs(owner());
    $boar = semenBoar();
    passSemenQc(collectSemen($boar));

    Livewire::test(SemenProduction::class)->assertSee($boar->animal_number)->assertSee('1 collections')
        ->set('from', 'garbage')->assertSee('format')->assertDontSee($boar->animal_number)
        ->set('from', now()->toDateString())->set('to', now()->subDays(3)->toDateString())->assertSee('on or after the start date');
    expectPeriodLimit(Livewire::test(SemenProduction::class), $boar->animal_number);
});

it('links a price to the breed\'s semen item', function () {
    $this->actingAs(owner());
    $item = InventoryItem::firstWhere('code', 'SEMEN-DUR');
    $list = PriceList::create(['farm_id' => Farm::first()->id, 'category_id' => lookup(LookupCategory::PriceCategory, 'semen'), 'code' => 'SEM-2026', 'name' => 'Semen 2026', 'currency_code' => 'NGN', 'is_active' => true]);

    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $list, 'pageClass' => EditPriceList::class])
        ->callAction(TestAction::make('create')->table(), ['code' => 'dur', 'description' => 'Duroc dose', 'unit_id' => UnitOfMeasure::firstWhere('code', 'DOSE')->id, 'unit_price_minor' => '15000.00', 'inventory_item_id' => $item->id])
        ->assertHasNoFormErrors();

    $batch = releasedSemen();
    semenBatchPage($batch)->assertSee('NGN 15,000.00');
    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $list, 'pageClass' => EditPriceList::class])->assertSee('Semen dose - Duroc');

    // A price list in another currency is shown in its own currency, not the farm's.
    $list->update(['currency_code' => 'USD']);
    semenBatchPage($batch)->assertSee('USD 15,000.00')->assertDontSee('NGN 15,000.00');
});

it('inseminates a sow with a released batch from the service form', function () {
    $this->actingAs(owner());
    $batch = releasedSemen();
    $sow = register();

    Livewire::test(CreateBreedingService::class)
        ->fillForm(['sow_id' => $sow->id, 'serviced_on' => now()->toDateString(), 'method' => 'artificial_insemination', 'semen_batch_id' => $batch->id, 'semen_location_id' => store('SEMEN')->id])
        ->call('create')->assertHasNoFormErrors();

    $service = BreedingService::sole();
    expect($service->semen_batch_id)->toBe($batch->id)->and($service->boar_id)->toBe($batch->animal_id)
        ->and(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'SEMEN-DUR')->id))->toBe('23.000');

    // A quarantined batch is no longer offered.
    app(ManageSemenBatch::class)->quarantine($batch, 'Recall');
    Livewire::test(CreateBreedingService::class)
        ->fillForm(['sow_id' => register()->id, 'serviced_on' => now()->toDateString(), 'method' => 'artificial_insemination', 'semen_batch_id' => $batch->id, 'semen_location_id' => store('SEMEN')->id])
        ->call('create')->assertHasFormErrors(['semen_batch_id']);
});

it('keeps the semen screens away from people without semen rights', function () {
    $this->actingAs(userWithRole('Slaughter Manager'));

    foreach ([SemenStock::getUrl(), SemenProduction::getUrl(), SemenBatchResource::getUrl('index'), SemenBoarResource::getUrl('index')] as $url) {
        $this->get($url)->assertForbidden();
    }

    $this->actingAs(userWithRole('Sales Officer'));
    $this->get(SemenStock::getUrl())->assertOk();
    $this->get(SemenBatchResource::getUrl('create'))->assertForbidden();
});
