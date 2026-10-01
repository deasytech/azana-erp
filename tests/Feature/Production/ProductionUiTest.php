<?php

use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Production\Actions\AddAnimalToBatch;
use App\Domain\Production\Actions\RemovePigsFromBatch;
use App\Domain\Production\Models\BatchWeighIn;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionCost;
use App\Enums\BatchEventType;
use App\Enums\BatchStatus;
use App\Enums\LookupCategory;
use App\Filament\Pages\ProductionOverview;
use App\Filament\Resources\Animals\Pages\ViewAnimal;
use App\Filament\Resources\FeedConsumption\FeedConsumptionResource;
use App\Filament\Resources\FeedConsumption\Pages\CreateFeedConsumption;
use App\Filament\Resources\FeedConsumption\Pages\ListFeedConsumption;
use App\Filament\Resources\FeedTypes\FeedTypeResource;
use App\Filament\Resources\FeedTypes\Pages\CreateFeedType;
use App\Filament\Resources\ProductionBatches\Pages\CreateProductionBatch;
use App\Filament\Resources\ProductionBatches\Pages\ViewProductionBatch;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Filament\Resources\ProductionBatches\RelationManagers\CostsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\FeedRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\WeighInsRelationManager;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function batchPage(ProductionBatch $batch)
{
    return Livewire::test(ViewProductionBatch::class, ['record' => $batch->getRouteKey()]);
}

it('renders every production page for the owner', function () {
    $this->actingAs(owner());
    $batch = scenario();

    $this->get(ProductionBatchResource::getUrl('index'))->assertOk()->assertSee($batch->code);
    $this->get(ProductionBatchResource::getUrl('create'))->assertOk();
    $this->get(ProductionBatchResource::getUrl('view', ['record' => $batch]))->assertOk()->assertSee('Performance');
    $this->get(FeedTypeResource::getUrl('index'))->assertOk();
    $this->get(FeedTypeResource::getUrl('create'))->assertOk();
    $this->get(FeedConsumptionResource::getUrl('index'))->assertOk();
    $this->get(FeedConsumptionResource::getUrl('create'))->assertOk();
    $this->get(ProductionOverview::getUrl())->assertOk()->assertSee($batch->code);
});

it('starts a batch through the form and lands on its page', function () {
    $this->actingAs(owner());

    Livewire::test(CreateProductionBatch::class)
        ->fillForm([
            'name' => 'Weaner group A', 'stage_id' => categoryId('weaner'), 'started_on' => now()->toDateString(), 'count' => 60,
            'average_weight_kg' => 8.5, 'unit_cost_minor' => '15000.50', 'placed_age_days' => 35,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $batch = ProductionBatch::firstOrFail();
    expect($batch->headCount())->toBe(60)
        ->and($batch->events->first()->unit_cost_minor)->toBe(1500050)
        ->and($batch->weighIns->first()->average_weight_kg)->toBe('8.50');

    Livewire::test(CreateProductionBatch::class)
        ->fillForm(['name' => 'Bad', 'stage_id' => categoryId('weaner'), 'started_on' => now()->toDateString(), 'count' => 10, 'average_weight_kg' => 9999])
        ->call('create')
        ->assertNotified('Not saved');
    expect(ProductionBatch::count())->toBe(1);
});

it('runs every batch action from its page', function () {
    $this->actingAs(owner());
    $batch = openBatch(['count' => 50, 'started_on' => now()->subDays(10)->startOfDay()]);
    $animal = register(['category_id' => categoryId('grower')]);
    $page = batchPage($batch);
    $today = now()->toDateString();

    $page->callAction('add_pigs', ['type' => 'transfer_in', 'count' => 5, 'occurred_on' => $today, 'unit_cost_minor' => '1000'])->assertNotified('Pigs added');
    $page->callAction('remove_pigs', ['type' => 'sale', 'count' => 3, 'occurred_on' => $today])->assertNotified('Pigs removed');
    $page->callAction('mortality', ['count' => 2, 'occurred_on' => $today, 'cause_id' => lookup(LookupCategory::MortalityCause, 'scours')])->assertNotified('Deaths recorded');
    $page->callAction('weigh', ['weighed_on' => $today, 'sample_size' => 20, 'average_weight_kg' => 30.5])->assertNotified('Weigh-in recorded');
    $page->callAction('feed', ['feed_type_id' => growerFeed()->id, 'consumed_on' => $today, 'quantity_kg' => 120, 'cost_per_kg_minor' => '250.50'])->assertNotified('Feed recorded');
    $page->callAction('cost', ['incurred_on' => $today, 'category' => 'labour', 'amount_minor' => '5000', 'description' => 'Wages'])->assertNotified('Cost recorded');
    $page->callAction('add_animal', ['animal_id' => $animal->id, 'joined_on' => $today])->assertNotified('Animal added to the batch');
    $page->callAction('adjust', ['delta' => -1, 'occurred_on' => $today, 'reason' => 'Recount'])->assertNotified('Count adjusted');

    expect($batch->headCount())->toBe(50 + 5 - 3 - 2 + 1 - 1)
        ->and(BatchWeighIn::whereDate('weighed_on', $today)->first()->average_weight_kg)->toBe('30.50')
        ->and(FeedConsumptionRecord::first()->cost_minor)->toBe(3006000)
        ->and(ProductionCost::first()->amount_minor)->toBe(500000);
});

it('shows rule violations from batch actions as notifications without saving', function () {
    $this->actingAs(owner());
    $batch = openBatch(['count' => 5]);

    batchPage($batch)->callAction('remove_pigs', ['type' => 'sale', 'count' => 50, 'occurred_on' => now()->toDateString()])->assertNotified('Not saved');
    batchPage($batch)->callAction('weigh', ['weighed_on' => now()->toDateString(), 'sample_size' => 99, 'average_weight_kg' => 30])->assertNotified('Not saved');

    expect($batch->headCount())->toBe(5)->and(BatchWeighIn::count())->toBe(0);
});

it('hides recording actions on a closed batch', function () {
    $this->actingAs(owner());
    $batch = openBatch(['count' => 2]);
    app(RemovePigsFromBatch::class)($batch, BatchEventType::Sale, 2, now()->startOfDay());

    expect($batch->fresh()->status)->toBe(BatchStatus::Closed);
    batchPage($batch->fresh())->assertActionHidden('add_pigs')->assertActionHidden('weigh')->assertActionHidden('feed');
});

it('shows performance and costs on the batch page', function () {
    $this->actingAs(owner());
    $batch = scenario();

    batchPage($batch)
        ->assertSee('1.000 kg/day')          // ADG
        ->assertSee('2.50')                   // FCR
        ->assertSee('2.00% (2 pigs)')
        ->assertSee('2940.00 kg')
        ->assertSee(now()->addDays(50)->format('d M Y'))
        ->assertSee('NGN 24,056.12')          // cost per pig
        ->assertSee('NGN 631.80');            // cost per kg gained
});

it('measures a chosen period and recovers from a bad one', function () {
    $this->actingAs(owner());
    $batch = scenario();
    $page = batchPage($batch);

    $page->callAction('period', ['from' => now()->subDays(15)->toDateString(), 'to' => now()->toDateString()])
        ->assertSee('2.96')->assertSee('4350.00 kg')->assertSee('15 days');

    $page->callAction('period', ['from' => now()->toDateString(), 'to' => now()->subDays(15)->toDateString()])
        ->assertSee('The period must end after it starts.')
        ->assertSee('2.50');                   // fell back to the whole-life figures
});

it('survives tampered period properties by falling back to whole-life figures', function () {
    $this->actingAs(owner());
    $batch = scenario();

    batchPage($batch)
        ->set('periodFrom', 'not-a-date')
        ->assertSee('The chosen period is not valid.')
        ->assertSet('periodFrom', null)->assertSet('periodTo', null)
        ->assertSee('2.50');
});

it('refreshes the batch page when a history tab voids a record', function () {
    $this->actingAs(owner());
    $batch = scenario();
    $page = batchPage($batch)->assertSee('2.50')->assertSee('7350.00 kg');
    $latest = BatchWeighIn::where('production_batch_id', $batch->id)->orderByDesc('weighed_on')->first();

    Livewire::test(WeighInsRelationManager::class, ['ownerRecord' => $batch, 'pageClass' => ViewProductionBatch::class])
        ->callAction(TestAction::make('void')->table($latest), ['reason' => 'Wrong batch weighed'])
        ->assertDispatched('production-batch-changed');

    // The tab is a separate component; the page refreshes when it hears the event and now measures to day 15.
    $page->dispatch('production-batch-changed')->assertSee('3000.00 kg')->assertDontSee('7350.00 kg');
});

it('voids weigh-ins, feed and costs from the history tabs', function () {
    $this->actingAs(owner());
    $batch = scenario();
    $weighIn = BatchWeighIn::where('production_batch_id', $batch->id)->orderByDesc('weighed_on')->first();
    $feed = FeedConsumptionRecord::first();
    $cost = ProductionCost::first();
    $tab = fn (string $manager) => Livewire::test($manager, ['ownerRecord' => $batch, 'pageClass' => ViewProductionBatch::class]);

    $tab(WeighInsRelationManager::class)->callAction(TestAction::make('void')->table($weighIn), ['reason' => 'Wrong batch'])->assertNotified('Record voided');
    $tab(FeedRelationManager::class)->callAction(TestAction::make('void')->table($feed), ['reason' => 'Typo'])->assertNotified('Record voided');
    $tab(CostsRelationManager::class)->callAction(TestAction::make('void')->table($cost), ['reason' => 'Duplicate'])->assertNotified('Record voided');

    expect($weighIn->fresh()->isVoided())->toBeTrue()->and($feed->fresh()->isVoided())->toBeTrue()->and($cost->fresh()->isVoided())->toBeTrue();
});

it('records feed for a batch or an animal from the feed records screen', function () {
    $this->actingAs(owner());
    $batch = openBatch();
    $animal = register();

    Livewire::test(CreateFeedConsumption::class)
        ->fillForm(['target' => 'batch', 'production_batch_id' => $batch->id, 'feed_type_id' => growerFeed()->id, 'consumed_on' => now()->toDateString(), 'quantity_kg' => 80, 'cost_per_kg_minor' => '300'])
        ->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateFeedConsumption::class)
        ->fillForm(['target' => 'animal', 'animal_id' => $animal->id, 'feed_type_id' => growerFeed()->id, 'consumed_on' => now()->toDateString(), 'quantity_kg' => 3])
        ->call('create')->assertHasNoFormErrors();

    expect(FeedConsumptionRecord::count())->toBe(2)
        ->and(FeedConsumptionRecord::where('production_batch_id', $batch->id)->first()->cost_minor)->toBe(2400000)
        ->and(FeedConsumptionRecord::where('animal_id', $animal->id)->exists())->toBeTrue();

    $record = FeedConsumptionRecord::where('animal_id', $animal->id)->first();
    Livewire::test(ListFeedConsumption::class)
        ->callAction(TestAction::make('void')->table($record), ['reason' => 'Typo'])
        ->assertNotified('Feed record voided');
    expect($record->fresh()->isVoided())->toBeTrue();
});

it('creates feed types and guards those in use', function () {
    $this->actingAs(owner());

    Livewire::test(CreateFeedType::class)->fillForm(['code' => 'custom', 'name' => 'Custom mix'])->call('create')->assertHasNoFormErrors();
    $type = FeedType::firstWhere('code', 'CUSTOM');
    expect($type->is_active)->toBeTrue()->and(owner()->can('delete', $type))->toBeTrue();

    feed(openBatch(), '10');
    expect(owner()->can('delete', growerFeed()))->toBeFalse();
});

it('shows active batches side by side on the overview', function () {
    $this->actingAs(owner());
    $active = scenario();
    $closed = openBatch(['name' => 'Gone', 'count' => 1]);
    app(RemovePigsFromBatch::class)($closed, BatchEventType::Sale, 1, now()->startOfDay());

    Livewire::test(ProductionOverview::class)
        ->assertSee($active->code)->assertSee('2.50')->assertSee('1.000')
        ->assertDontSee($closed->code);
});

it('shows growth and batch on a tracked animal\'s profile', function () {
    $this->actingAs(owner());
    $batch = openBatch();
    $animal = register(['birth_date' => now()->subDays(100)->toDateString()]);
    app(AddAnimalToBatch::class)($batch, $animal, now()->startOfDay());
    $weigh = app(RecordWeight::class);
    $weigh($animal, '30', now()->subDays(10));
    $weigh($animal, '40', now());
    app(RecordFeedConsumption::class)($animal, growerFeed(), now()->subDays(3), '25');

    Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()])
        ->assertSee($batch->code)->assertSee('1.000 kg/day')->assertSee('10.00 kg over 10 days')->assertSee('2.50');
});

it('applies production permission defaults to the screens', function () {
    $batch = scenario();

    $this->actingAs(farmWorker());
    $this->get(ProductionBatchResource::getUrl('index'))->assertOk();
    batchPage($batch)->assertActionVisible('weigh')->assertActionVisible('feed')->assertActionHidden('adjust');

    $this->actingAs(userWithRole('Feed Mill Manager'));
    $this->get(ProductionBatchResource::getUrl('view', ['record' => $batch]))->assertOk();
    batchPage($batch)->assertActionHidden('weigh');
    $this->get(ProductionBatchResource::getUrl('create'))->assertForbidden();

    $this->actingAs(userWithRole('Farm Manager'));
    batchPage($batch)->assertActionVisible('adjust');

    $this->actingAs(userWithRole('Semen Laboratory Manager'));
    $this->get(ProductionBatchResource::getUrl('index'))->assertForbidden();
    $this->get(ProductionOverview::getUrl())->assertForbidden();
});
