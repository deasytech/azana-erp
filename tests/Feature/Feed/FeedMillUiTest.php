<?php

use App\Domain\Feed\Actions\ConfirmFeedConsumption;
use App\Domain\Feed\Actions\ManageFeedFormulaVersions;
use App\Domain\Feed\Actions\SaveFeedFormula;
use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Feed\Models\FeedProductionOrder;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Enums\FeedProductionStatus as Status;
use App\Enums\FormulaStatus;
use App\Filament\Resources\FeedFormulas\FeedFormulaResource;
use App\Filament\Resources\FeedFormulas\Pages\CreateFeedFormula;
use App\Filament\Resources\FeedFormulas\Pages\EditFeedFormula;
use App\Filament\Resources\FeedFormulas\Pages\ViewFeedFormula;
use App\Filament\Resources\FeedFormulas\RelationManagers\IngredientsRelationManager;
use App\Filament\Resources\FeedProductionBatches\FeedProductionBatchResource;
use App\Filament\Resources\FeedProductionOrders\FeedProductionOrderResource;
use App\Filament\Resources\FeedProductionOrders\Pages\CreateFeedProductionOrder;
use App\Filament\Resources\FeedProductionOrders\Pages\ViewFeedProductionOrder;
use App\Filament\Resources\FeedProductionOrders\RelationManagers\MaterialsRelationManager;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function orderPage(FeedProductionOrder $order)
{
    return Livewire::test(ViewFeedProductionOrder::class, ['record' => $order->getRouteKey()]);
}

it('renders every feed mill page for the owner', function () {
    $this->actingAs(owner());
    $mill = millFixture();
    $batch = completedFeedRun($mill);

    foreach ([
        FeedFormulaResource::getUrl('index'), FeedFormulaResource::getUrl('create'), FeedFormulaResource::getUrl('view', ['record' => $mill['formula']]),
        FeedProductionOrderResource::getUrl('index'), FeedProductionOrderResource::getUrl('create'), FeedProductionOrderResource::getUrl('view', ['record' => $batch->order]),
        FeedProductionBatchResource::getUrl('index'),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(FeedProductionBatchResource::getUrl('index'))->assertSee($batch->order->number);
});

it('creates a formula through the form and shows its ingredients and total', function () {
    $this->actingAs(userWithRole('Nutritionist'));
    $maize = stockItem('MAIZE');
    $soya = stockItem('SOYA');

    Livewire::test(CreateFeedFormula::class)
        ->fillForm(['code' => 'sow-lact', 'name' => 'Sow lactation', 'feed_type_id' => growerFeed()->id, 'process_loss_percent' => 1.5, 'crude_protein_percent' => 17.5,
            'items' => [['inventory_item_id' => $maize->id, 'inclusion_percent' => '70'], ['inventory_item_id' => $soya->id, 'inclusion_percent' => '25']]])
        ->assertSee('Ingredients add up to 95.0000%')
        ->call('create')->assertHasNoFormErrors();

    $formula = FeedFormula::sole();
    expect($formula->code)->toBe('SOW-LACT')->and($formula->status)->toBe(FormulaStatus::Draft)->and($formula->items)->toHaveCount(2);

    Livewire::test(CreateFeedFormula::class)
        ->fillForm(['code' => 'sow-lact', 'name' => 'Again', 'feed_type_id' => growerFeed()->id, 'items' => [['inventory_item_id' => $maize->id, 'inclusion_percent' => '100']]])
        ->call('create')->assertHasFormErrors(['code']);
});

it('edits a draft formula but not an active one', function () {
    $this->actingAs(userWithRole('Nutritionist'));
    $mill = millFixture();
    $draft = app(ManageFeedFormulaVersions::class)->newVersion($mill['formula']);

    Livewire::test(EditFeedFormula::class, ['record' => $draft->getRouteKey()])
        ->assertFormSet(['name' => 'Grower standard'])
        ->fillForm(['name' => 'Grower standard B', 'items' => [
            ['inventory_item_id' => $mill['maize']->id, 'inclusion_percent' => '65'], ['inventory_item_id' => $mill['soya']->id, 'inclusion_percent' => '25'], ['inventory_item_id' => $mill['premix']->id, 'inclusion_percent' => '10'],
        ]])
        ->call('save')->assertHasNoFormErrors();

    expect($draft->fresh()->name)->toBe('Grower standard B')->and((string) $draft->items()->where('inventory_item_id', $mill['maize']->id)->first()->inclusion_percent)->toBe('65.0000');

    expect(FeedFormulaResource::canEdit($mill['formula']))->toBeFalse()->and(FeedFormulaResource::canEdit($draft))->toBeTrue();
    $this->get(FeedFormulaResource::getUrl('edit', ['record' => $mill['formula']]))->assertForbidden();
});

it('shows a formula\'s nutrition, cost and ingredients', function () {
    $this->actingAs(owner());
    $mill = millFixture();

    Livewire::test(ViewFeedFormula::class, ['record' => $mill['formula']->getRouteKey()])
        ->assertSee('GROWER-STD')->assertSee('16.50')->assertSee('3100.00')
        ->assertSee('NGN 693.88')            // per kg
        ->assertSee('NGN 17,346.94')         // per 25 kg bag
        ->assertSee('NGN 693,877.55');       // per tonne

    Livewire::test(IngredientsRelationManager::class, ['ownerRecord' => $mill['formula'], 'pageClass' => ViewFeedFormula::class])
        ->assertSee('Item MAIZE')->assertSee('60.0000%')->assertSee('612.245');
});

it('keeps ingredients with the same name apart on the ingredients tab', function () {
    $this->actingAs(owner());
    $cheap = stockItem('MAIZE-A', ['name' => 'Maize']);
    $dear = stockItem('MAIZE-B', ['name' => 'Maize']);
    receiveStock($cheap, '100', 10000);
    receiveStock($dear, '100', 90000);
    $formula = app(SaveFeedFormula::class)(['code' => 'twins', 'name' => 'Twins', 'feed_type_id' => growerFeed()->id, 'items' => [
        ['inventory_item_id' => $cheap->id, 'inclusion_percent' => '50'], ['inventory_item_id' => $dear->id, 'inclusion_percent' => '50'],
    ]]);

    Livewire::test(IngredientsRelationManager::class, ['ownerRecord' => $formula, 'pageClass' => ViewFeedFormula::class])
        ->assertSee('NGN 100.00')->assertSee('NGN 900.00');   // each line shows its own item's cost, not the other's
});

it('activates a draft, starts a new version and retires from the formula page', function () {
    $this->actingAs(owner());
    stockItem('GROWER-MEAL', ['feed_type_id' => growerFeed()->id]);
    $draft = app(SaveFeedFormula::class)(['code' => 'ok-1', 'name' => 'OK', 'feed_type_id' => growerFeed()->id, 'items' => [['inventory_item_id' => stockItem('MAIZE')->id, 'inclusion_percent' => '100']]]);
    $page = fn ($f) => Livewire::test(ViewFeedFormula::class, ['record' => $f->getRouteKey()]);

    $page($draft)->assertActionVisible('activate')->assertActionHidden('new_version')->callAction('activate')->assertNotified('Formula activated');
    expect($draft->fresh()->status)->toBe(FormulaStatus::Active);

    $page($draft)->assertActionHidden('activate')->callAction('new_version')->assertNotified('Version 2 started')->assertRedirect();
    expect(FeedFormula::where('code', 'OK-1')->count())->toBe(2);
    $page($draft)->callAction('new_version')->assertNotified('Not saved');   // a draft already exists

    $page($draft)->callAction('retire')->assertNotified('Formula retired');
    expect($draft->fresh()->status)->toBe(FormulaStatus::Retired);
});

it('plans an order through the form and shows what it needs', function () {
    $this->actingAs(owner());
    $mill = millFixture();

    Livewire::test(CreateFeedProductionOrder::class)
        ->fillForm(['feed_formula_id' => $mill['formula']->id, 'planned_output_kg' => 1000, 'planned_on' => now()->toDateString(),
            'source_location_id' => $mill['raw']->id, 'output_location_id' => $mill['out']->id])
        ->call('create')->assertHasNoFormErrors();

    $order = FeedProductionOrder::sole();
    expect($order->lines)->toHaveCount(3);

    Livewire::test(MaterialsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewFeedProductionOrder::class])
        ->assertSee('Item MAIZE')->assertSee('612.245')->assertSee('Not confirmed');
});

it('confirms quantities, completes the run and shows its cost', function () {
    $this->actingAs(owner());
    $mill = millFixture();
    $order = plannedFeedOrder($mill);
    $line = fn ($item, $qty) => ['inventory_item_id' => $item->id, 'quantity' => $qty];

    orderPage($order)->assertActionVisible('confirm')->callAction('complete', ['output_kg' => 990, 'produced_on' => now()->toDateString()])->assertNotified('Not saved');
    expect($order->fresh()->status)->toBe(Status::Planned);

    orderPage($order)->callAction('confirm', ['lines' => [$line($mill['maize'], '612.245'), $line($mill['soya'], '306.122'), $line($mill['premix'], '102.041')]])->assertNotified('Quantities confirmed');

    orderPage($order)
        ->callAction('complete', ['output_kg' => 990, 'produced_on' => now()->toDateString(), 'other_cost_minor' => '10000.00'])
        ->assertNotified('Production completed')
        ->assertActionHidden('complete')->assertActionHidden('cancel')
        ->assertSee('NGN 703,877.55')          // total cost
        ->assertSee('NGN 710.99')              // per kg
        ->assertSee('99.00%');                 // 990 of 1000 kg

    $batch = FeedProductionBatch::sole();
    expect($batch->total_cost_minor)->toBe(70387755)->and(app(GetStockLevels::class)->total($mill['finished']->id))->toBe('990.000');
});

it('uses planned quantities in one click', function () {
    $this->actingAs(owner());
    $order = plannedFeedOrder(millFixture());

    orderPage($order)->callAction('as_planned')->assertNotified('Quantities confirmed as planned');

    expect($order->fresh()->lines->every(fn ($l) => $l->isConfirmed()))->toBeTrue();
    orderPage($order)->callAction('complete', ['output_kg' => 1000, 'produced_on' => now()->toDateString()])->assertNotified('Production completed');
});

it('shows a shortage as a notification and posts nothing', function () {
    $this->actingAs(owner());
    $mill = millFixture();
    $order = plannedFeedOrder($mill, '5000');
    app(ConfirmFeedConsumption::class)->asPlanned($order);

    orderPage($order)->callAction('complete', ['output_kg' => 5000, 'produced_on' => now()->toDateString()])->assertNotified('Not saved');

    expect(FeedProductionBatch::count())->toBe(0)->and($order->fresh()->status)->toBe(Status::Planned)->and(app(GetStockLevels::class)->total($mill['maize']->id))->toBe('1000.000');

    Livewire::test(MaterialsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewFeedProductionOrder::class])->assertSee('2061.224');
});

it('cancels a planned order', function () {
    $this->actingAs(owner());
    $order = plannedFeedOrder(millFixture());

    orderPage($order)->callAction('cancel', ['reason' => 'Plan changed'])->assertNotified('Order cancelled')->assertSee('Plan changed');

    expect($order->fresh()->status)->toBe(Status::Cancelled);
});

it('lets only someone with approval rights reverse a completed run', function () {
    $mill = millFixture();
    $order = completedFeedRun($mill)->order;

    $this->actingAs(userWithRole('Nutritionist'));
    orderPage($order)->assertActionHidden('reverse');

    $this->actingAs(userWithRole('Feed Mill Manager'));
    orderPage($order)->assertActionVisible('reverse')->callAction('reverse', ['reason' => 'Wrong formula'])->assertNotified('Production reversed');

    expect($order->fresh()->status)->toBe(Status::Reversed)->and(app(GetStockLevels::class)->total($mill['maize']->id))->toBe('1000.000');
    orderPage($order)->assertSee('Wrong formula');
});

it('refuses to reverse once the finished feed has been used', function () {
    $this->actingAs(owner());
    $mill = millFixture();
    $order = completedFeedRun($mill)->order;
    feed(openBatch(), '10', extra: ['inventory_location_id' => $mill['out']->id]);

    orderPage($order)->callAction('reverse', ['reason' => 'Oops'])->assertNotified('Not saved');

    expect($order->fresh()->status)->toBe(Status::Completed);
});

it('keeps the feed mill away from people without feed mill rights', function () {
    $this->actingAs(farmWorker());

    foreach ([FeedFormulaResource::getUrl('index'), FeedProductionOrderResource::getUrl('index'), FeedProductionBatchResource::getUrl('index')] as $url) {
        $this->get($url)->assertForbidden();
    }

    // Viewing is not creating: the store officer can look at formulas but not make them.
    $this->actingAs(userWithRole('Store Officer'));
    $this->get(FeedFormulaResource::getUrl('index'))->assertOk();
    $this->get(FeedFormulaResource::getUrl('create'))->assertForbidden();
    $this->get(FeedProductionOrderResource::getUrl('create'))->assertForbidden();

    $this->actingAs(userWithRole('Veterinarian'));
    $this->get(FeedFormulaResource::getUrl('index'))->assertForbidden();
});
