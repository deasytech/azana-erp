<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\Building;
use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\FarmSetting;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Farm\Models\PriceList;
use App\Domain\Farm\Models\PriceListItem;
use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\Farm\Models\Room;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\LookupCategory;
use App\Filament\Resources\Breeds\BreedResource;
use App\Filament\Resources\Breeds\Pages\CreateBreed;
use App\Filament\Resources\Buildings\BuildingResource;
use App\Filament\Resources\Farms\FarmResource;
use App\Filament\Resources\FarmSettings\FarmSettingResource;
use App\Filament\Resources\FarmSettings\Pages\EditFarmSetting;
use App\Filament\Resources\GeneticLines\GeneticLineResource;
use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\LookupValues\LookupValueResource;
use App\Filament\Resources\LookupValues\Pages\CreateLookupValue;
use App\Filament\Resources\Pens\Pages\CreatePen;
use App\Filament\Resources\Pens\PenResource;
use App\Filament\Resources\PriceLists\PriceListResource;
use App\Filament\Resources\ProductionUnits\Pages\CreateProductionUnit;
use App\Filament\Resources\ProductionUnits\ProductionUnitResource;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\UnitsOfMeasure\Pages\CreateUnitOfMeasure;
use App\Filament\Resources\UnitsOfMeasure\Pages\EditUnitOfMeasure;
use App\Filament\Resources\UnitsOfMeasure\UnitOfMeasureResource;
use App\Models\AuditLog;
use App\Models\Role;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function lookupId(LookupCategory $category, string $code): int
{
    return LookupValue::where('category', $category->value)->where('code', $code)->value('id');
}

function makeUnit(string $code = 'PU2'): ProductionUnit
{
    return ProductionUnit::create([
        'farm_id' => Farm::first()->id, 'type_id' => lookupId(LookupCategory::ProductionUnitType, 'piggery'),
        'code' => $code, 'name' => "Unit {$code}",
    ]);
}

function makeBuilding(?ProductionUnit $unit = null, string $code = 'B1'): Building
{
    return Building::create([
        'production_unit_id' => ($unit ?? ProductionUnit::first())->id,
        'type_id' => lookupId(LookupCategory::BuildingType, 'farrowing_house'), 'code' => $code, 'name' => "Building {$code}",
    ]);
}

function makeRoom(Building $building, string $code = 'R1'): Room
{
    return Room::create(['building_id' => $building->id, 'code' => $code, 'name' => "Room {$code}"]);
}

function penData(Building $building, array $extra = []): array
{
    return array_merge([
        'building_id' => $building->id, 'purpose_id' => lookupId(LookupCategory::PenPurpose, 'farrowing'), 'code' => 'P1',
    ], $extra);
}

it('seeds starting master data idempotently', function () {
    $this->seed(MasterDataSeeder::class);

    expect(Farm::count())->toBe(1)
        ->and(ProductionUnit::count())->toBe(4)
        ->and(Breed::count())->toBe(6)
        ->and(FarmSetting::count())->toBe(28)
        ->and(UnitOfMeasure::where('code', 'KG')->exists())->toBeTrue();
});

it('models the full hierarchy company -> unit -> building -> room -> pen', function () {
    $pen = Pen::create(penData($building = makeBuilding(), ['room_id' => makeRoom($building)->id]));

    expect($pen->room->building->productionUnit->farm->code)->toBe('IPAF')
        ->and($pen->building->is($building))->toBeTrue();
});

it('lets an admin add production units through the UI without code changes', function () {
    $this->actingAs(owner());

    Livewire::test(CreateProductionUnit::class)
        ->fillForm([
            'code' => 'hatch', 'name' => 'Hatchery', 'farm_id' => Farm::first()->id,
            'type_id' => lookupId(LookupCategory::ProductionUnitType, 'other'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProductionUnit::firstWhere('code', 'HATCH')->name)->toBe('Hatchery');
});

it('rejects a pen whose room is in another building (domain rule and database)', function () {
    $a = makeBuilding(null, 'A');
    $b = makeBuilding(null, 'B');
    $roomInB = makeRoom($b, 'RB');

    expect(fn () => Pen::create(penData($a, ['room_id' => $roomInB->id])))
        ->toThrow(DomainException::class, 'does not belong');

    // Bypass the model: the composite foreign key still refuses it.
    expect(fn () => DB::table('pens')->insert([
        'building_id' => $a->id, 'room_id' => $roomInB->id, 'purpose_id' => lookupId(LookupCategory::PenPurpose, 'farrowing'),
        'code' => 'BAD', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects locations whose building is outside the production unit', function () {
    $building = makeBuilding(ProductionUnit::firstWhere('code', 'PIG'));
    $otherUnit = ProductionUnit::firstWhere('code', 'FM');
    $type = lookupId(LookupCategory::LocationType, 'store');

    expect(fn () => Location::create(['production_unit_id' => $otherUnit->id, 'building_id' => $building->id, 'type_id' => $type, 'code' => 'L1', 'name' => 'Bad']))
        ->toThrow(DomainException::class);

    expect(fn () => Location::create(['production_unit_id' => $building->production_unit_id, 'room_id' => makeRoom($building, 'RX')->id, 'type_id' => $type, 'code' => 'L2', 'name' => 'No building']))
        ->toThrow(DomainException::class);

    $ok = Location::create(['production_unit_id' => $building->production_unit_id, 'building_id' => $building->id, 'room_id' => makeRoom($building, 'RY')->id, 'type_id' => $type, 'code' => 'L3', 'name' => 'Fine']);
    expect($ok->exists)->toBeTrue();
});

it('keeps pens valid when created through the form', function () {
    $this->actingAs(owner());
    $a = makeBuilding(null, 'A');
    $roomInB = makeRoom(makeBuilding(null, 'B'), 'RB');

    Livewire::test(CreatePen::class)
        ->fillForm(penData($a, ['room_id' => $roomInB->id]))
        ->call('create');

    expect(Pen::count())->toBe(0);

    Livewire::test(CreatePen::class)
        ->fillForm(penData($a, ['code' => 'ok-1']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Pen::firstWhere('code', 'OK-1'))->not->toBeNull();
});

it('enforces unique, normalised business identifiers', function () {
    makeBuilding(null, ' b1 ');

    expect(Building::first()->code)->toBe('B1')
        ->and(fn () => makeBuilding(null, 'b1'))->toThrow(QueryException::class)
        ->and(fn () => Breed::create(['code' => 'lw', 'name' => 'Dup']))->toThrow(QueryException::class)
        ->and(fn () => UnitOfMeasure::create(['code' => 'kg', 'name' => 'Dup', 'category' => 'mass']))->toThrow(QueryException::class)
        ->and(fn () => LookupValue::create(['category' => 'pen_purpose', 'code' => 'Boar', 'name' => 'Dup']))->toThrow(QueryException::class);
});

it('never deletes master data that is in use', function () {
    $owner = owner();
    $unit = ProductionUnit::firstWhere('code', 'PIG');
    makeBuilding($unit);

    expect($owner->can('delete', $unit))->toBeFalse()
        ->and($owner->can('delete', makeUnit('EMPTY')))->toBeTrue()
        ->and($owner->can('delete', LookupValue::firstWhere('code', 'farrowing_house')))->toBeFalse()
        ->and(fn () => DB::table('production_units')->where('id', $unit->id)->delete())->toThrow(QueryException::class);
});

it('reads typed settings with defaults, and stores edits per farm', function () {
    $settings = app(ResolveSettings::class);

    expect($settings->get('breeding.gestation_days'))->toBe(114)
        ->and($settings->get('production.target_dressing_percent'))->toBe('75');

    $settings->set('breeding.gestation_days', 115);
    expect($settings->get('breeding.gestation_days'))->toBe(115);

    // Missing row falls back to the registered default.
    FarmSetting::where('key', 'breeding.gestation_days')->delete();
    expect($settings->get('breeding.gestation_days'))->toBe(114);
});

it('validates setting values and unknown keys', function () {
    $settings = app(ResolveSettings::class);

    expect(fn () => $settings->set('breeding.gestation_days', 'abc'))->toThrow(DomainException::class)
        ->and(fn () => $settings->set('breeding.gestation_days', -1))->toThrow(DomainException::class)
        ->and(fn () => $settings->get('nope.nothing'))->toThrow(InvalidArgumentException::class);
});

it('never overwrites edited settings when defaults are re-applied', function () {
    $settings = app(ResolveSettings::class);
    $settings->set('breeding.weaning_age_days', 21);

    $settings->ensureDefaults(Farm::first());

    expect($settings->get('breeding.weaning_age_days'))->toBe(21);
});

it('edits settings through the UI with validation and an audit trail', function () {
    $this->actingAs($owner = owner());
    $row = FarmSetting::firstWhere('key', 'breeding.pregnancy_check_days');

    Livewire::test(EditFarmSetting::class, ['record' => $row->getRouteKey()])
        ->fillForm(['value' => 'abc'])
        ->call('save');
    expect($row->fresh()->value)->toBe('28');

    Livewire::test(EditFarmSetting::class, ['record' => $row->getRouteKey()])
        ->fillForm(['value' => '30'])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = AuditLog::where('event', 'updated')->where('auditable_type', $row->getMorphClass())->where('auditable_id', $row->id)->first();
    expect($row->fresh()->value)->toBe('30')
        ->and($log->old_values)->toBe(['value' => '28'])
        ->and($log->user_id)->toBe($owner->id);
});

it('converts between units of the same family using exact decimals', function () {
    $kg = UnitOfMeasure::firstWhere('code', 'KG');
    $ton = UnitOfMeasure::firstWhere('code', 'TON');
    $g = UnitOfMeasure::firstWhere('code', 'G');

    expect($ton->convertTo('2', $kg))->toBe('2000.00000000')
        ->and($g->convertTo('500', $kg))->toBe('0.50000000')
        ->and($kg->convertTo('1', $g))->toBe('1000.00000000')
        ->and($kg->convertTo('7', $kg))->toBe('7')
        ->and(fn () => $kg->convertTo('1', UnitOfMeasure::firstWhere('code', 'L')))->toThrow(DomainException::class);
});

it('stores prices as integer minor units', function () {
    $list = PriceList::create([
        'farm_id' => Farm::first()->id, 'category_id' => lookupId(LookupCategory::PriceCategory, 'pig'),
        'code' => 'pig-2026', 'name' => 'Pig prices 2026', 'currency_code' => 'NGN',
    ]);
    $item = PriceListItem::create([
        'price_list_id' => $list->id, 'code' => 'finisher-kg', 'description' => 'Finisher, per kg live weight',
        'unit_id' => UnitOfMeasure::firstWhere('code', 'KG')->id, 'unit_price_minor' => 285050,
    ]);

    expect($item->fresh()->unit_price_minor)->toBe(285050)
        ->and($item->unitPrice()->format())->toBe('NGN 2,850.50')
        ->and($list->code)->toBe('PIG-2026');
});

it('applies the role defaults for the new modules', function () {
    $worker = farmWorker();
    $manager = userWithRole('Farm Manager');
    $accountant = userWithRole('Accountant');
    $sales = userWithRole('Sales Officer');

    expect($worker->can('viewAny', Pen::class))->toBeTrue()
        ->and($worker->can('create', Pen::class))->toBeFalse()
        ->and($worker->can('viewAny', PriceList::class))->toBeFalse()
        ->and($manager->can('create', Pen::class))->toBeTrue()
        ->and($manager->can('create', Breed::class))->toBeFalse()
        ->and($accountant->can('create', PriceList::class))->toBeTrue()
        ->and($sales->can('viewAny', PriceList::class))->toBeTrue()
        ->and($sales->can('create', PriceList::class))->toBeFalse()
        ->and($manager->can('viewAny', FarmSetting::class))->toBeTrue()
        ->and($manager->can('update', FarmSetting::first()))->toBeFalse();
});

it('never lets settings be created or deleted by hand', function () {
    $owner = owner();

    expect($owner->can('create', FarmSetting::class))->toBeFalse()
        ->and($owner->can('delete', FarmSetting::first()))->toBeFalse();
});

it('re-seeding grants only newly created permissions and never reverts admin edits', function () {
    $worker = Role::findByName('Farm Worker');
    $worker->revokePermissionTo('farm-structure.view');

    $this->seed(RoleSeeder::class);
    expect($worker->fresh()->hasPermissionTo('farm-structure.view'))->toBeFalse();

    // A brand-new permission (as a later phase would add) reaches the default roles.
    Permission::where('name', 'settings.edit')->first()->delete();
    $this->seed(RoleSeeder::class);

    expect(Role::findByName('General Manager')->hasPermissionTo('settings.edit'))->toBeTrue();
});

it('finds farm structure through global search', function () {
    $this->actingAs(owner());
    Pen::create(penData(makeBuilding(), ['code' => 'FARROW-77']));

    $results = Filament::getGlobalSearchProvider()->getResults('FARROW-77');
    $labels = collect($results->getCategories())->flatten(1)->map(fn ($r) => $r->title)->all();

    expect($labels)->toContain('FARROW-77');
});

it('hides farm master data from users without permission', function () {
    $this->actingAs(farmWorker());

    $this->get(PriceListResource::getUrl('index'))->assertForbidden();
    $this->get(PenResource::getUrl('index'))->assertOk();
});

it('renders every farm and master data admin page for the owner', function () {
    $this->actingAs(owner());
    $building = makeBuilding();
    $pen = Pen::create(penData($building));
    $list = PriceList::create([
        'farm_id' => Farm::first()->id, 'category_id' => lookupId(LookupCategory::PriceCategory, 'pig'),
        'code' => 'PL', 'name' => 'PL', 'currency_code' => 'NGN',
    ]);

    $resources = [
        FarmResource::class => Farm::first(),
        ProductionUnitResource::class => ProductionUnit::first(),
        BuildingResource::class => $building,
        PenResource::class => $pen,
        BreedResource::class => Breed::first(),
        UnitOfMeasureResource::class => UnitOfMeasure::first(),
        LookupValueResource::class => LookupValue::first(),
        FarmSettingResource::class => FarmSetting::first(),
        PriceListResource::class => $list,
    ];

    foreach ($resources as $resource => $record) {
        $this->get($resource::getUrl('index'))->assertOk();
        $this->get($resource::getUrl('edit', ['record' => $record]))->assertOk();
    }

    foreach ([RoomResource::class, LocationResource::class, GeneticLineResource::class] as $resource) {
        $this->get($resource::getUrl('index'))->assertOk();
        $this->get($resource::getUrl('create'))->assertOk();
    }
});

it('checks code uniqueness against the normalised value', function () {
    $this->actingAs(owner());

    Livewire::test(CreateBreed::class)
        ->fillForm(['code' => ' lw ', 'name' => 'Dup', 'species' => 'pig'])
        ->call('create')
        ->assertHasFormErrors(['code']);

    Livewire::test(CreateLookupValue::class)
        ->fillForm(['category' => 'pen_purpose', 'code' => ' BOAR ', 'name' => 'Dup'])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('only offers root units as a base and requires a positive factor', function () {
    $this->actingAs(owner());
    $kg = UnitOfMeasure::firstWhere('code', 'KG');
    $ton = UnitOfMeasure::firstWhere('code', 'TON');

    $component = Livewire::test(EditUnitOfMeasure::class, ['record' => $kg->getRouteKey()]);
    $component->assertFormFieldDisabled('base_unit_id'); // TON, G are based on kg: kg must stay a root

    Livewire::test(CreateUnitOfMeasure::class)
        ->fillForm(['code' => 'QTL', 'name' => 'Quintal', 'category' => 'mass', 'base_unit_id' => $ton->id, 'conversion_factor' => '100'])
        ->call('create')
        ->assertHasFormErrors(['base_unit_id']);

    Livewire::test(CreateUnitOfMeasure::class)
        ->fillForm(['code' => 'QTL', 'name' => 'Quintal', 'category' => 'mass', 'base_unit_id' => $kg->id, 'conversion_factor' => '0'])
        ->call('create')
        ->assertHasFormErrors(['conversion_factor']);

    Livewire::test(CreateUnitOfMeasure::class)
        ->fillForm(['code' => 'QTL', 'name' => 'Quintal', 'category' => 'mass', 'base_unit_id' => $kg->id, 'conversion_factor' => '100'])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('blocks moving a room or building that still has dependants', function () {
    $unit = ProductionUnit::firstWhere('code', 'PIG');
    $building = makeBuilding($unit, 'BA');
    $other = makeBuilding($unit, 'BB');
    $room = makeRoom($building);
    Pen::create(penData($building, ['room_id' => $room->id]));

    expect(fn () => $room->update(['building_id' => $other->id]))->toThrow(DomainException::class, 'cannot be moved');

    Location::create(['production_unit_id' => $unit->id, 'building_id' => $building->id, 'type_id' => lookupId(LookupCategory::LocationType, 'store'), 'code' => 'LX', 'name' => 'X']);
    expect(fn () => $building->update(['production_unit_id' => ProductionUnit::firstWhere('code', 'FM')->id]))
        ->toThrow(DomainException::class, 'cannot be moved');

    // Without dependants, moving is fine.
    $free = makeRoom($other, 'RFREE');
    $free->update(['building_id' => $building->id]);
    expect($free->fresh()->building_id)->toBe($building->id);

    $this->actingAs(owner());
    Livewire::test(EditRoom::class, ['record' => $room->getRouteKey()])
        ->assertFormFieldDisabled('building_id');
});
