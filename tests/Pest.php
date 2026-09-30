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
use App\Domain\Health\Actions\RecordTreatment;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\MedicineBatch;
use App\Domain\Litter\Models\Litter;
use App\Enums\LookupCategory;
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
