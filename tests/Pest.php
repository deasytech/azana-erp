<?php

use App\Domain\Animal\Actions\RegisterAnimal;
use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Actions\RecordFarrowing;
use App\Domain\Breeding\Actions\RecordService;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\Building;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Actions\CompleteFeedProduction;
use App\Domain\Feed\Actions\ConfirmFeedConsumption;
use App\Domain\Feed\Actions\CreateFeedProductionOrder;
use App\Domain\Feed\Actions\ManageFeedFormulaVersions;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Feed\Actions\SaveFeedFormula;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Feed\Models\FeedProductionOrder;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Health\Actions\RecordTreatment;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\MedicineBatch;
use App\Domain\Inventory\Actions\IssueStock;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Litter\Models\Litter;
use App\Domain\Meat\Actions\ProduceMeat;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Procurement\Actions\CreatePurchaseOrder;
use App\Domain\Procurement\Actions\CreatePurchaseRequest;
use App\Domain\Procurement\Actions\DecidePurchaseOrder;
use App\Domain\Procurement\Actions\DecidePurchaseRequest;
use App\Domain\Procurement\Actions\ReceiveGoods;
use App\Domain\Procurement\Models\GoodsReceipt;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Production\Actions\OpenProductionBatch;
use App\Domain\Production\Actions\RecordBatchMortality;
use App\Domain\Production\Actions\RecordBatchWeighIn;
use App\Domain\Production\Actions\RecordProductionCost;
use App\Domain\Production\Models\BatchWeighIn;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Sales\Actions\ConfirmSalesOrder;
use App\Domain\Sales\Actions\CreateSalesOrder;
use App\Domain\Sales\Actions\DispatchSalesOrder;
use App\Domain\Sales\Actions\RecordCustomerPayment;
use App\Domain\Sales\Actions\SaveCustomer;
use App\Domain\Sales\Actions\SetCustomerCredit;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Semen\Actions\ManageSemenBoar;
use App\Domain\Semen\Actions\ProcessSemenBatch;
use App\Domain\Semen\Actions\RecordSemenCollection;
use App\Domain\Semen\Actions\RecordSemenQc;
use App\Domain\Semen\Actions\ReleaseSemenBatch;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Slaughter\Actions\ManageSlaughterBatch;
use App\Domain\Slaughter\Actions\RecordSlaughter;
use App\Domain\Slaughter\Actions\RecordSlaughterIntake;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\Slaughter\Models\SlaughterBatch;
use App\Domain\Slaughter\Models\SlaughterRecord;
use App\Domain\Supplier\Models\Supplier;
use App\Enums\AnteMortemResult;
use App\Enums\CreditStatus;
use App\Enums\InventoryCategory;
use App\Enums\InventoryTransactionType;
use App\Enums\LookupCategory;
use App\Enums\PostMortemResult;
use App\Enums\ProductionCostCategory;
use App\Enums\ReceiptMethod;
use App\Enums\ServiceMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** Creates a user holding the given role (roles must already be seeded). */
function userWithRole(string $role, array $attrs = []): User
{
    return User::factory()->create($attrs)->assignRole($role);
}

/** An active Owner/Director. */
function owner(array $attrs = []): User
{
    return userWithRole(Role::OWNER, $attrs);
}

/** An active Farm Worker (a low-privilege operational role). */
function farmWorker(): User
{
    return userWithRole('Farm Worker');
}

/** Id of an animal category lookup by code. */
function categoryId(string $code): int
{
    return LookupValue::where('category', LookupCategory::AnimalCategory->value)->where('code', $code)->value('id');
}

/** A pen in a shared test building. */
function newPen(string $code = 'P1', ?int $capacity = null): Pen
{
    static $building = null;
    $building = Building::firstOrCreate(['code' => 'ABLD'], [
        'production_unit_id' => ProductionUnit::firstWhere('code', 'PIG')->id,
        'type_id' => LookupValue::where('category', LookupCategory::BuildingType->value)->value('id'), 'name' => 'Animal building',
    ]);

    return Pen::create([
        'building_id' => $building->id, 'code' => $code, 'capacity' => $capacity,
        'purpose_id' => LookupValue::where('category', LookupCategory::PenPurpose->value)->value('id'),
    ]);
}

/** Registers an animal through the real domain action (default: a born-on-farm sow). */
function register(array $overrides = []): Animal
{
    return app(RegisterAnimal::class)(array_merge([
        'sex' => 'female', 'category_id' => categoryId('sow'), 'source' => 'born_on_farm',
    ], $overrides));
}

/** A registered boar. */
function boar(): Animal
{
    return register(['sex' => 'male', 'category_id' => categoryId('boar')]);
}

/** Serves a sow naturally $daysAgo days ago (default: exactly one gestation ago). */
function serve(Animal $sow, int $daysAgo = 114, ?Animal $boar = null): BreedingService
{
    return app(RecordService::class)($sow, ServiceMethod::Natural, now()->subDays($daysAgo)->startOfDay(), ($boar ?? boar())->id);
}

/** Records a default farrowing (12 born: 10 alive, 1 stillborn, 1 mummified) today. */
function farrow(Animal $sow, array $data = []): Litter
{
    return app(RecordFarrowing::class)($sow, array_merge([
        'farrowed_on' => now()->startOfDay(), 'total_born' => 12, 'born_alive' => 10, 'stillborn' => 1, 'mummified' => 1,
    ], $data));
}

/** Id of a lookup value by category and code. */
function lookup(LookupCategory $category, string $code): int
{
    return LookupValue::where('category', $category->value)->where('code', $code)->value('id');
}

/** A medicine of the given type ("other" by default) with the given withdrawal days. */
function medicine(int $withdrawalDays = 0, string $type = 'other', string $code = 'MED1'): Medicine
{
    return Medicine::firstOrCreate(['code' => $code], [
        'name' => "Medicine {$code}", 'type_id' => lookup(LookupCategory::MedicineType, $type), 'default_withdrawal_days' => $withdrawalDays,
    ]);
}

function vaccine(string $code = 'VAC1', int $withdrawalDays = 0): Medicine
{
    return medicine($withdrawalDays, 'vaccine', $code);
}

function batchOf(Medicine $medicine, string $number = 'B-1', ?string $expires = null): MedicineBatch
{
    return MedicineBatch::create([
        'medicine_id' => $medicine->id, 'batch_number' => $number, 'expiry_date' => $expires ?? now()->addYear()->toDateString(),
    ]);
}

/** Treats an animal (default: today) with the given medicine. */
function treat($animal, $medicine, ?string $on = null, array $options = [])
{
    return app(RecordTreatment::class)($animal, $medicine, $on ? now()->parse($on)->startOfDay() : now()->startOfDay(), $options);
}

/** Id of a mortality cause lookup by code. */
function cause(string $code = 'unknown'): int
{
    return lookup(LookupCategory::MortalityCause, $code);
}

function openBatch(array $overrides = []): ProductionBatch
{
    return app(OpenProductionBatch::class)(array_merge([
        'name' => 'Grower batch', 'stage_id' => categoryId('grower'), 'started_on' => now()->startOfDay(), 'count' => 100,
    ], $overrides));
}

function growerFeed(): FeedType
{
    return FeedType::firstWhere('code', 'GROWER');
}

function feed(ProductionBatch $batch, string $kg, int $daysAgo = 0, ?int $perKg = null, array $extra = []): FeedConsumptionRecord
{
    return app(RecordFeedConsumption::class)($batch, growerFeed(), now()->subDays($daysAgo)->startOfDay(), $kg, ['cost_per_kg_minor' => $perKg] + $extra);
}

function weighIn(ProductionBatch $batch, string $avg, int $daysAgo = 0, int $sample = 20): BatchWeighIn
{
    return app(RecordBatchWeighIn::class)($batch, now()->subDays($daysAgo)->startOfDay(), $sample, $avg);
}

/** 100 pigs placed 30 days ago at 20 kg, 2 died, weighed at day 15 (35 kg) and today (50 kg). */
function scenario(): ProductionBatch
{
    $batch = openBatch(['started_on' => now()->subDays(30)->startOfDay(), 'count' => 100, 'average_weight_kg' => '20.00', 'unit_cost_minor' => 500000]);
    app(RecordBatchMortality::class)($batch, 2, now()->subDays(10)->startOfDay(), lookup(LookupCategory::MortalityCause, 'scours'));
    weighIn($batch, '35.00', 15, 50);
    weighIn($batch, '50.00', 0, 50);
    feed($batch, '3000', 20, 25000);        // day 10: before the day-15 weigh-in
    feed($batch, '2000', 10, 25000);        // day 20
    feed($batch, '2350', 2, 25000);         // day 28
    app(RecordProductionCost::class)($batch, now()->subDays(3), ProductionCostCategory::Labour, 2000000, 'Labour');

    return $batch;
}

/** A stock item measured in kilograms (feed ingredient by default). */
function stockItem(string $code = 'MAIZE', array $attrs = []): InventoryItem
{
    return InventoryItem::firstOrCreate(['code' => $code], $attrs + [
        'name' => "Item {$code}", 'category' => InventoryCategory::FeedIngredient, 'unit_id' => UnitOfMeasure::firstWhere('code', 'KG')->id,
    ]);
}

/** A store (inventory location) by code. */
function store(string $code = 'MAIN'): InventoryLocation
{
    return InventoryLocation::firstOrCreate(['code' => $code], ['name' => "Store {$code}"]);
}

/** Receives stock into a store ($unitCost in minor units per unit), dated $daysAgo days back. */
function receiveStock(InventoryItem $item, string $quantity, int $unitCost, ?InventoryLocation $store = null, int $daysAgo = 0, array $details = []): InventoryTransaction
{
    return app(ReceiveStock::class)(InventoryTransactionType::Receipt, $item, $store ?? store(), $quantity, now()->subDays($daysAgo)->startOfDay(), ['unit_cost_minor' => $unitCost] + $details);
}

/** Uses stock (consumption by default); returns the ledger lines. */
function issueStock(InventoryItem $item, string $quantity, ?InventoryLocation $store = null, InventoryTransactionType $type = InventoryTransactionType::Consumption, array $details = [])
{
    return app(IssueStock::class)($type, $item, $store ?? store(), $quantity, now()->startOfDay(), $details);
}

function supplier(string $code = 'SUP1', int $terms = 30): Supplier
{
    return Supplier::firstOrCreate(['code' => $code], ['name' => "Supplier {$code}", 'payment_terms_days' => $terms]);
}

/**
 * An approved purchase order (default: 100 kg of maize at 350.00 per kg = 3,500,000 minor in total).
 *
 * @param  list<array<string, mixed>>|null  $lines
 */
function purchaseOrder(?array $lines = null, ?Supplier $supplier = null, bool $approve = true): PurchaseOrder
{
    $order = app(CreatePurchaseOrder::class)($supplier ?? supplier(), $lines ?? [
        ['inventory_item_id' => stockItem()->id, 'quantity' => '100', 'unit_cost_minor' => 35000],
    ], now()->subDays(10)->startOfDay());

    if ($approve) {
        app(DecidePurchaseOrder::class)->submit($order);
        app(DecidePurchaseOrder::class)->approve($order, userWithRole('Farm Manager'));
    }

    return $order->refresh();
}

/** Receives the given quantities against the order's lines, in order (null skips a line). */
function receiveGoods(PurchaseOrder $order, array $quantities, ?InventoryLocation $store = null, array $extra = []): GoodsReceipt
{
    $lines = $order->lines->values()->map(fn ($line, $i) => isset($quantities[$i]) ? ['purchase_order_line_id' => $line->id, 'quantity' => $quantities[$i]] + ($extra[$i] ?? []) : null)->filter()->values()->all();

    return app(ReceiveGoods::class)($order, $store ?? store(), now()->startOfDay(), $lines);
}

/** A purchase request raised by $clerk and approved by $manager. */
function approvedRequest($clerk, $manager)
{
    $decide = app(DecidePurchaseRequest::class);
    $request = app(CreatePurchaseRequest::class)([
        ['inventory_item_id' => stockItem()->id, 'quantity' => '100', 'estimated_unit_cost_minor' => 35000],
    ], now()->addDays(7), 'Running low', $clerk);
    $decide->submit($request);

    return $decide->approve($request, $manager);
}

/**
 * A feed mill ready to work: raw stock in the RAW store (1000 kg maize @350.00, 500 kg soya @900.00, 200 kg premix
 *
 * @2000.00 in batch PX-1), a stock item for finished grower meal (tracks expiry) and an active formula of
 * 60% maize / 30% soya / 10% premix with 2% process loss.
 *
 * @return array{maize: InventoryItem, soya: InventoryItem, premix: InventoryItem, finished: InventoryItem, raw: InventoryLocation, out: InventoryLocation, formula: FeedFormula}
 */
function millFixture(): array
{
    $maize = stockItem('MAIZE');
    $soya = stockItem('SOYA');
    $premix = stockItem('PREMIX', ['tracks_batches' => true]);
    $finished = stockItem('GROWER-MEAL', ['category' => InventoryCategory::FinishedFeed, 'feed_type_id' => growerFeed()->id, 'tracks_expiry' => true]);
    $raw = store('RAW');

    receiveStock($maize, '1000', 35000, $raw, 5);
    receiveStock($soya, '500', 90000, $raw, 5);
    receiveStock($premix, '200', 200000, $raw, 5, ['batch_number' => 'PX-1', 'supplier_id' => supplier()->id]);

    $formula = app(SaveFeedFormula::class)([
        'code' => 'grower-std', 'name' => 'Grower standard', 'feed_type_id' => growerFeed()->id, 'process_loss_percent' => '2',
        'crude_protein_percent' => '16.5', 'energy_kcal_per_kg' => '3100',
        'items' => [
            ['inventory_item_id' => $maize->id, 'inclusion_percent' => '60'],
            ['inventory_item_id' => $soya->id, 'inclusion_percent' => '30'],
            ['inventory_item_id' => $premix->id, 'inclusion_percent' => '10'],
        ],
    ]);

    return ['maize' => $maize, 'soya' => $soya, 'premix' => $premix, 'finished' => $finished, 'raw' => $raw, 'out' => store('FINISHED'),
        'formula' => app(ManageFeedFormulaVersions::class)->activate($formula)];
}

function plannedFeedOrder(array $mill, string $outputKg = '1000'): FeedProductionOrder
{
    return app(CreateFeedProductionOrder::class)($mill['formula'], $outputKg, now()->startOfDay(), $mill['raw'], $mill['out']);
}

/** Order for 1000 kg, materials confirmed as planned, completed with 990 kg made and 10,000.00 of other costs. */
function completedFeedRun(array $mill): FeedProductionBatch
{
    $order = plannedFeedOrder($mill);
    app(ConfirmFeedConsumption::class)->asPlanned($order);

    return app(CompleteFeedProduction::class)($order, '990', now()->startOfDay(), 1000000);
}

/** A registered boar of the breed (Duroc by default) already in the semen programme. */
function semenBoar(string $breedCode = 'DUR', array $programme = []): Animal
{
    $boar = register(['sex' => 'male', 'category_id' => categoryId('boar'), 'breed_id' => Breed::firstWhere('code', $breedCode)->id]);
    app(ManageSemenBoar::class)->enrol($boar, $programme);

    return $boar;
}

/** Collects 250 ml (unless told otherwise) from the boar $daysAgo days ago; returns the new batch. */
function collectSemen(Animal $boar, int $daysAgo = 0, string $volume = '250'): SemenBatch
{
    return app(RecordSemenCollection::class)($boar, now()->subDays($daysAgo), $volume);
}

/** Records a passing QC (80% motile, 300 million/ml, 10% abnormal) as a laboratory manager. */
function passSemenQc(SemenBatch $batch, ?User $analyst = null): SemenBatch
{
    app(RecordSemenQc::class)($batch, '80', '300', '10', null, $analyst ?? userWithRole('Semen Laboratory Manager'));

    return $batch->refresh();
}

/** A batch that passed QC and was processed into the most doses it can yield (24 for the standard 250 ml sample). */
function processedSemen(?Animal $boar = null): SemenBatch
{
    $batch = passSemenQc(collectSemen($boar ?? semenBoar()));
    app(ProcessSemenBatch::class)($batch, 24, '80', 'BTS');

    return $batch->refresh();
}

/** A processed batch released into the SEMEN store by a farm manager. */
function releasedSemen(?Animal $boar = null): SemenBatch
{
    return app(ReleaseSemenBatch::class)(processedSemen($boar), userWithRole('Farm Manager'), store('SEMEN'));
}

/** A customer (cash terms until credit is approved). */
function customer(array $over = []): Customer
{
    return app(SaveCustomer::class)($over + ['name' => 'Green Acres Farm', 'customer_type_id' => lookup(LookupCategory::CustomerType, 'farmer')]);
}

/** A customer with approved credit (default limit 10,000,000.00, 30-day terms). */
function creditCustomer(int $limitMinor = 1000000000, int $terms = 30, array $over = []): Customer
{
    return app(SetCustomerCredit::class)(customer($over), CreditStatus::Approved, $limitMinor, $terms, userWithRole('Farm Manager'));
}

/** A draft order of semen doses from the batch at 15,000.00 a dose (unless a price is given). */
function semenOrder(Customer $customer, SemenBatch $batch, int $doses = 10, array $line = [], ?string $daysAgo = null): SalesOrder
{
    return app(CreateSalesOrder::class)($customer, [[
        'kind' => 'semen', 'semen_batch_id' => $batch->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => $doses, 'unit_price_minor' => 1500000,
    ] + $line], now()->subDays((int) $daysAgo)->startOfDay());
}

/** A confirmed order of the draft, confirmed by a farm manager. */
function confirmed(SalesOrder $order): SalesOrder
{
    return app(ConfirmSalesOrder::class)($order, userWithRole('Farm Manager'));
}

/** Confirms and dispatches an order (today unless $daysAgo); returns its invoice. */
function dispatched(SalesOrder $order, int $daysAgo = 0): Invoice
{
    $daysAgo > 0 && $order->update(['ordered_on' => now()->subDays($daysAgo)->startOfDay()]);   // an older sale was ordered then too

    return app(DispatchSalesOrder::class)(confirmed($order), now()->subDays($daysAgo)->startOfDay(), 'DN-1');
}

function pay(Customer $customer, int $minor, ?array $allocations = null, int $daysAgo = 0): Payment
{
    return app(RecordCustomerPayment::class)($customer, $minor, ReceiptMethod::BankTransfer, now()->subDays($daysAgo)->startOfDay(), null, $allocations);
}

/** A slaughter day scheduled for today. */
function slaughterDay(): SlaughterBatch
{
    return app(ManageSlaughterBatch::class)->schedule(now()->startOfDay());
}

/** Receives a grower pig (a new one unless given) at 100.00 kg live weight, costing 20,000.00 to raise, and passes inspection. */
function receivePig(SlaughterBatch $day, ?Animal $pig = null, string $liveKg = '100.00', ?int $cost = 2000000): SlaughterRecord
{
    return app(RecordSlaughterIntake::class)($day, $pig ?? register(['category_id' => categoryId('grower')]), $liveKg, AnteMortemResult::Passed, null, 1, $cost);
}

/** Receives and slaughters a pig: 100.00 kg live, 76.00 kg hot carcass (76% dressing), passed fit for food. */
function slaughterPig(?SlaughterBatch $day = null, string $hotKg = '76.00', ?int $cost = 2000000): Carcass
{
    return app(RecordSlaughter::class)(receivePig($day ?? slaughterDay(), cost: $cost), $hotKg, PostMortemResult::Passed);
}

/** @param array<string, string> $weights kilograms by product code, e.g. ['LEG' => '20.00'] */
function meatLines(array $weights): array
{
    return collect($weights)->map(fn ($kg, $code) => ['meat_product_id' => MeatProduct::firstWhere('code', $code)->id, 'weight_kg' => $kg])->values()->all();
}

/** Makes meat from the carcass: leg 20, loin 15, shoulder 18, belly 12 and liver 2 kg, 6 kg waste, 1,000.00 other cost. */
function makeMeat(?Carcass $carcass = null): MeatProductionBatch
{
    return app(ProduceMeat::class)([($carcass ?? slaughterPig())->id], store('COLD1'), now()->startOfDay(),
        meatLines(['LEG' => '20.00', 'LOIN' => '15.00', 'SHOULDER' => '18.00', 'BELLY' => '12.00', 'LIVER' => '2.00']), '6.00', 100000);
}
