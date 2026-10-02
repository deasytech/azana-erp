<?php

use App\Domain\Animal\Actions\RegisterAnimal;
use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Actions\RecordFarrowing;
use App\Domain\Breeding\Actions\RecordService;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Farm\Models\Building;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Feed\Models\FeedConsumptionRecord;
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
use App\Domain\Production\Actions\OpenProductionBatch;
use App\Domain\Production\Actions\RecordBatchMortality;
use App\Domain\Production\Actions\RecordBatchWeighIn;
use App\Domain\Production\Actions\RecordProductionCost;
use App\Domain\Production\Models\BatchWeighIn;
use App\Domain\Production\Models\ProductionBatch;
use App\Enums\InventoryCategory;
use App\Enums\InventoryTransactionType;
use App\Enums\LookupCategory;
use App\Enums\ProductionCostCategory;
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
