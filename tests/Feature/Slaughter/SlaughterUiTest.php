<?php

use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\Slaughter\Models\SlaughterBatch;
use App\Domain\Slaughter\Models\SlaughterRecord;
use App\Enums\AnimalStatus;
use App\Enums\CarcassStatus;
use App\Enums\InventoryCategory;
use App\Enums\MeatProductionStatus;
use App\Enums\SlaughterBatchStatus;
use App\Enums\SlaughterRecordStatus as R;
use App\Filament\Pages\MeatStock;
use App\Filament\Pages\SlaughterYield;
use App\Filament\Resources\Carcasses\CarcassResource;
use App\Filament\Resources\Carcasses\Pages\ListCarcasses;
use App\Filament\Resources\MeatProductionBatches\MeatProductionBatchResource;
use App\Filament\Resources\MeatProductionBatches\Pages\CreateMeatProductionBatch;
use App\Filament\Resources\MeatProductionBatches\Pages\ViewMeatProductionBatch;
use App\Filament\Resources\MeatProductionBatches\RelationManagers\LinesRelationManager;
use App\Filament\Resources\MeatProductionBatches\RelationManagers\SourcesRelationManager;
use App\Filament\Resources\MeatProducts\MeatProductResource;
use App\Filament\Resources\MeatProducts\Pages\CreateMeatProduct;
use App\Filament\Resources\SlaughterBatches\Pages\CreateSlaughterBatch;
use App\Filament\Resources\SlaughterBatches\Pages\ViewSlaughterBatch;
use App\Filament\Resources\SlaughterBatches\RelationManagers\RecordsRelationManager;
use App\Filament\Resources\SlaughterBatches\SlaughterBatchResource;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function dayPage(SlaughterBatch $day)
{
    return Livewire::test(ViewSlaughterBatch::class, ['record' => $day->getRouteKey()]);
}

it('renders every slaughter and meat page for the owner', function () {
    $this->actingAs(owner());
    $meat = makeMeat();
    $day = SlaughterBatch::first();

    foreach ([
        MeatStock::getUrl(), SlaughterYield::getUrl(),
        SlaughterBatchResource::getUrl('index'), SlaughterBatchResource::getUrl('create'), SlaughterBatchResource::getUrl('view', ['record' => $day]),
        CarcassResource::getUrl('index'),
        MeatProductionBatchResource::getUrl('index'), MeatProductionBatchResource::getUrl('create'), MeatProductionBatchResource::getUrl('view', ['record' => $meat]),
        MeatProductResource::getUrl('index'), MeatProductResource::getUrl('create'), MeatProductResource::getUrl('edit', ['record' => MeatProduct::first()]),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(MeatStock::getUrl())->assertSee($meat->number)->assertSee('Leg');
});

it('schedules a slaughter day and receives, inspects and slaughters a pig through the screens', function () {
    $this->actingAs(owner());
    $pig = register(['category_id' => categoryId('grower')]);

    Livewire::test(CreateSlaughterBatch::class)->fillForm(['scheduled_on' => now()->toDateString(), 'notes' => 'Friday'])->call('create')->assertHasNoFormErrors();
    $day = SlaughterBatch::sole();
    expect($day->number)->toBe('SB-000001');

    dayPage($day)->assertActionVisible('receive')
        ->callAction('receive', ['source' => 'animal', 'animal_id' => $pig->id, 'live_weight_kg' => 100, 'ante_mortem' => 'passed', 'live_cost_minor' => '20000.00'])
        ->assertNotified('Pig received');
    $record = SlaughterRecord::sole();
    expect($record->status)->toBe(R::Received)->and($record->live_cost_minor)->toBe(2000000);

    Livewire::test(RecordsRelationManager::class, ['ownerRecord' => $day, 'pageClass' => ViewSlaughterBatch::class])
        ->assertSee($pig->animal_number)
        ->callAction(TestAction::make('slaughter')->table($record), ['hot_weight_kg' => 76, 'post_mortem' => 'passed'])->assertNotified('Slaughter recorded');

    $carcass = Carcass::sole();
    expect((string) $carcass->dressing_percent)->toBe('76.00')->and($pig->fresh()->status)->toBe(AnimalStatus::Slaughtered);
    Livewire::test(RecordsRelationManager::class, ['ownerRecord' => $day, 'pageClass' => ViewSlaughterBatch::class])->assertSee('76.00')->assertSee($carcass->number);

    dayPage($day)->callAction('complete')->assertNotified('Slaughter day closed');
    expect($day->fresh()->status)->toBe(SlaughterBatchStatus::Completed);
    dayPage($day)->assertActionHidden('receive')->assertActionHidden('complete');
});

it('rejects a pig at inspection and shows rule violations as notifications', function () {
    $this->actingAs(owner());
    $day = slaughterDay();
    $pig = register(['category_id' => categoryId('grower')]);
    $treated = register(['category_id' => categoryId('grower')]);
    treat($treated, medicine(30), now()->subDay()->toDateString());

    dayPage($day)->callAction('receive', ['source' => 'animal', 'animal_id' => $pig->id, 'live_weight_kg' => 90, 'ante_mortem' => 'failed'])->assertNotified('Not saved');
    dayPage($day)->callAction('receive', ['source' => 'animal', 'animal_id' => $pig->id, 'live_weight_kg' => 90, 'ante_mortem' => 'failed', 'notes' => 'Feverish'])->assertNotified('Pig rejected at inspection');
    dayPage($day)->callAction('receive', ['source' => 'animal', 'animal_id' => $treated->id, 'live_weight_kg' => 90, 'ante_mortem' => 'passed'])->assertNotified('Not saved');

    expect(SlaughterRecord::sole()->status)->toBe(R::Rejected)->and($pig->fresh()->status)->toBe(AnimalStatus::Active);
});

it('receives a group of pigs from a batch', function () {
    $this->actingAs(owner());
    $batch = openBatch(['count' => 10, 'unit_cost_minor' => 500000]);
    $day = slaughterDay();

    dayPage($day)->callAction('receive', ['source' => 'batch', 'production_batch_id' => $batch->id, 'heads' => 3, 'live_weight_kg' => 300, 'ante_mortem' => 'passed'])->assertNotified('Pig received');

    expect(SlaughterRecord::sole())->heads->toBe(3)->live_cost_minor->toBe(1500000);
});

it('cancels a day and sends the pigs back', function () {
    $this->actingAs(owner());
    $day = slaughterDay();
    $record = receivePig($day);

    dayPage($day)->callAction('cancel', ['reason' => 'Power cut'])->assertNotified('Slaughter day cancelled');

    expect($day->fresh()->status)->toBe(SlaughterBatchStatus::Cancelled)->and($record->fresh()->status)->toBe(R::Rejected);
});

it('lists carcasses and corrects a weight only for someone who can approve', function () {
    $carcass = slaughterPig();

    $this->actingAs(userWithRole('Veterinarian'));
    Livewire::test(ListCarcasses::class)->assertCanSeeTableRecords([$carcass])->assertActionHidden(TestAction::make('adjust')->table($carcass));

    $this->actingAs(userWithRole('Slaughter Manager'));
    Livewire::test(ListCarcasses::class)->assertActionVisible(TestAction::make('adjust')->table($carcass))
        ->callAction(TestAction::make('adjust')->table($carcass), ['hot_weight_kg' => 74, 'reason' => 'Scale re-read'])->assertNotified('Carcass weight corrected');

    expect((string) $carcass->fresh()->dressing_percent)->toBe('74.00');
});

it('makes meat from carcasses through the form and shows its cost and where it came from', function () {
    $this->actingAs(owner());
    $carcass = slaughterPig();
    $product = fn (string $code) => MeatProduct::firstWhere('code', $code)->id;

    Livewire::test(CreateMeatProductionBatch::class)
        ->fillForm(['carcass_ids' => [$carcass->id], 'inventory_location_id' => store('COLD1')->id, 'produced_on' => now()->toDateString(), 'waste_kg' => 6, 'other_cost_minor' => '1000.00', 'lines' => [
            ['meat_product_id' => $product('LEG'), 'weight_kg' => 20], ['meat_product_id' => $product('LOIN'), 'weight_kg' => 15],
            ['meat_product_id' => $product('SHOULDER'), 'weight_kg' => 18], ['meat_product_id' => $product('BELLY'), 'weight_kg' => 12], ['meat_product_id' => $product('LIVER'), 'weight_kg' => 2],
        ]])
        ->call('create')->assertHasNoFormErrors();

    $batch = MeatProductionBatch::sole();
    expect($batch->total_cost_minor)->toBe(2100000)->and($batch->lines)->toHaveCount(5)->and($carcass->fresh()->status)->toBe(CarcassStatus::Processed)
        ->and(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'MEAT-LEG')->id))->toBe('20.000');

    Livewire::test(ViewMeatProductionBatch::class, ['record' => $batch->getRouteKey()])->assertSee('NGN 21,000.00')->assertSee($batch->number);
    Livewire::test(LinesRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewMeatProductionBatch::class])->assertSee('Leg')->assertSee('NGN 6,268.66');
    Livewire::test(SourcesRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewMeatProductionBatch::class])->assertSee($carcass->number)->assertSee($carcass->record->animal->animal_number)->assertSee('76.00');
});

it('refuses to make more meat than the carcasses weigh, with a notification', function () {
    $this->actingAs(owner());
    $carcass = slaughterPig();

    Livewire::test(CreateMeatProductionBatch::class)
        ->fillForm(['carcass_ids' => [$carcass->id], 'inventory_location_id' => store('COLD1')->id, 'produced_on' => now()->toDateString(), 'waste_kg' => 0,
            'lines' => [['meat_product_id' => MeatProduct::firstWhere('code', 'LEG')->id, 'weight_kg' => 80]]])
        ->call('create')->assertNotified('Not saved');

    expect(MeatProductionBatch::count())->toBe(0)->and($carcass->fresh()->status)->toBe(CarcassStatus::Hanging);
});

it('reverses a meat batch only for someone who can approve', function () {
    $batch = makeMeat();

    $this->actingAs(userWithRole('Veterinarian'));
    Livewire::test(ViewMeatProductionBatch::class, ['record' => $batch->getRouteKey()])->assertActionHidden('reverse');

    $this->actingAs(userWithRole('Slaughter Manager'));
    Livewire::test(ViewMeatProductionBatch::class, ['record' => $batch->getRouteKey()])->callAction('reverse', ['reason' => 'Wrong carcass'])->assertNotified('Meat production reversed')->assertSee('Wrong carcass');

    expect($batch->fresh()->status)->toBe(MeatProductionStatus::Reversed)->and(app(GetStockLevels::class)())->toBeEmpty();
});

it('manages the product catalogue', function () {
    $this->actingAs(owner());
    $item = InventoryItem::create(['code' => 'MEAT-SAUSAGE', 'name' => 'Sausage', 'category' => InventoryCategory::Meat, 'unit_id' => UnitOfMeasure::firstWhere('code', 'KG')->id, 'tracks_expiry' => true]);

    Livewire::test(CreateMeatProduct::class)
        ->fillForm(['code' => 'sausage', 'name' => 'Sausage', 'kind' => 'by_product', 'inventory_item_id' => $item->id, 'shelf_life_days' => 14])
        ->call('create')->assertHasNoFormErrors();

    expect(MeatProduct::firstWhere('code', 'SAUSAGE')->shelf_life_days)->toBe(14);

    // Meat is stocked by batch, so an item that is not tracked by batch is not offered.
    $untracked = InventoryItem::create(['code' => 'MEAT-LOOSE', 'name' => 'Loose', 'category' => InventoryCategory::Meat, 'unit_id' => $item->unit_id]);
    $untracked->forceFill(['tracks_batches' => false])->save();
    Livewire::test(CreateMeatProduct::class)
        ->fillForm(['code' => 'loose', 'name' => 'Loose', 'kind' => 'offal', 'inventory_item_id' => $untracked->id, 'shelf_life_days' => 3])
        ->call('create')->assertHasFormErrors(['inventory_item_id']);

    // A stock item can belong to only one product.
    Livewire::test(CreateMeatProduct::class)
        ->fillForm(['code' => 'other', 'name' => 'Other', 'kind' => 'offal', 'inventory_item_id' => $item->id, 'shelf_life_days' => 3])
        ->call('create')->assertHasFormErrors(['inventory_item_id']);
});

it('reports yield and survives a tampered period', function () {
    $this->actingAs(owner());
    slaughterPig();

    Livewire::test(SlaughterYield::class)->assertSee('1 carcasses')->assertSee('76.00%')->assertSee('SB-000001')
        ->set('from', 'garbage')->assertSee('format')->assertDontSee('SB-000001')
        ->set('from', now()->toDateString())->set('to', now()->subDays(3)->toDateString())->assertSee('on or after the start date');
    expectPeriodLimit(Livewire::test(SlaughterYield::class), 'SB-000001');
});

it('keeps the slaughter screens away from people without slaughter rights', function () {
    $this->actingAs(farmWorker());

    foreach ([SlaughterBatchResource::getUrl('index'), CarcassResource::getUrl('index'), MeatProductionBatchResource::getUrl('index'), MeatStock::getUrl(), SlaughterYield::getUrl(), MeatProductResource::getUrl('index')] as $url) {
        $this->get($url)->assertForbidden();
    }

    $this->actingAs(userWithRole('Sales Officer'));
    $this->get(MeatStock::getUrl())->assertOk();
    $this->get(SlaughterBatchResource::getUrl('create'))->assertForbidden();
});
