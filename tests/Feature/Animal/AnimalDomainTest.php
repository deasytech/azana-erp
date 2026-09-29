<?php

use App\Domain\Animal\Actions\AddAnimalIdentifier;
use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Animal\Actions\GetAnimalHistory;
use App\Domain\Animal\Actions\LookupAnimal;
use App\Domain\Animal\Actions\RecordAnimalMovement;
use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Actions\RetireAnimalIdentifier;
use App\Domain\Animal\Actions\SetAnimalParentage;
use App\Domain\Animal\Actions\VoidWeight;
use App\Domain\Animal\Events\AnimalMoved;
use App\Domain\Animal\Events\AnimalRegistered;
use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalMovement;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalSex;
use App\Enums\AnimalStatus;
use App\Enums\IdentifierType;
use App\Enums\LookupCategory;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

it('issues permanent, sequential numbers per category from the configured prefix', function () {
    $first = register();
    $second = register();
    $boar = register(['sex' => 'male', 'category_id' => categoryId('boar')]);

    expect($first->animal_number)->toBe('IPA-SOW-0001')
        ->and($second->animal_number)->toBe('IPA-SOW-0002')
        ->and($boar->animal_number)->toBe('IPA-BOAR-0001')
        ->and($first->public_id)->toHaveLength(26);

    app(ResolveSettings::class)->set('animals.number_prefix', 'zz');
    expect(register()->animal_number)->toBe('ZZ-SOW-0001');
});

it('registers with identifiers, parentage, placement and initial history in one step', function () {
    Event::fake([AnimalRegistered::class, AnimalMoved::class]);
    $pen = newPen();
    $sire = register(['sex' => 'male', 'category_id' => categoryId('boar')]);
    $dam = register();

    $piglet = register([
        'category_id' => categoryId('piglet'), 'birth_date' => now()->subDays(10)->toDateString(),
        'pen_id' => $pen->id, 'sire_id' => $sire->id, 'dam_id' => $dam->id,
        'identifiers' => [['type' => 'ear_tag', 'value' => ' t-100 '], ['type' => 'rfid', 'value' => 'e200abc']],
    ]);

    expect($piglet->identifiers->pluck('value')->all())->toBe(['T-100', 'E200ABC'])
        ->and($piglet->parentage->sire->is($sire))->toBeTrue()
        ->and($piglet->currentPen->is($pen))->toBeTrue()
        ->and($piglet->movements)->toHaveCount(1)
        ->and($piglet->statusHistory->first()->reason)->toBe('Registered')
        ->and($piglet->status)->toBe(AnimalStatus::Active);

    Event::assertDispatched(AnimalRegistered::class, 3);
});

it('rolls everything back when registration fails part-way', function () {
    $count = Animal::count();

    expect(fn () => register(['identifiers' => [['type' => 'ear_tag', 'value' => 'DUP'], ['type' => 'ear_tag', 'value' => 'DUP']]]))
        ->toThrow(DomainException::class);

    expect(Animal::count())->toBe($count);
});

it('validates registration input', function (array $bad, string $message) {
    expect(fn () => register($bad))->toThrow(DomainException::class, $message);
})->with([
    'female boar' => fn () => [['category_id' => categoryId('boar')], 'cannot be registered'],
    'unknown category' => [['category_id' => 999999], 'valid animal category'],
    'future birth date' => [['birth_date' => now()->addDay()->toDateString()], 'birth date cannot be in the future'],
    'future acquisition date' => [['acquired_on' => now()->addDay()->toDateString()], 'acquisition date cannot be in the future'],
    'future acquisition date with placement' => fn () => [['acquired_on' => now()->addDay()->toDateString(), 'pen_id' => newPen('FUT')->id], 'acquisition date cannot be in the future'],
]);

it('gives an animal several identifiers that all point to it', function () {
    $animal = register();
    $add = app(AddAnimalIdentifier::class);

    $add($animal, IdentifierType::EarTag, 'ET-1');
    $add($animal, IdentifierType::Rfid, '900123');
    $add($animal, IdentifierType::Barcode, 'BC-1');
    $add($animal, IdentifierType::Manual, 'OLD-SOW-7');

    $lookup = app(LookupAnimal::class);
    expect($animal->identifiers)->toHaveCount(4)
        ->and($lookup('et-1')->is($animal))->toBeTrue()
        ->and($lookup('900123')->is($animal))->toBeTrue()
        ->and($lookup(' old-sow-7 ')->is($animal))->toBeTrue();
});

it('never lets an identifier be shared or reused', function () {
    $a = register();
    $b = register();
    app(AddAnimalIdentifier::class)($a, IdentifierType::EarTag, 'ET-9');

    expect(fn () => app(AddAnimalIdentifier::class)($b, IdentifierType::EarTag, 'et-9'))->toThrow(DomainException::class, 'already registered')
        ->and(fn () => app(AddAnimalIdentifier::class)($b, IdentifierType::Manual, $a->animal_number))->toThrow(DomainException::class)
        ->and(fn () => $b->identifiers()->create(['type' => 'ear_tag', 'value' => 'ET-9']))->toThrow(QueryException::class);

    // Retiring frees nothing: the value stays reserved and stops resolving.
    $identifier = $a->identifiers()->first();
    app(RetireAnimalIdentifier::class)($identifier, 'Tag lost');
    expect(app(LookupAnimal::class)('ET-9'))->toBeNull()
        ->and(fn () => app(AddAnimalIdentifier::class)($b, IdentifierType::EarTag, 'ET-9'))->toThrow(DomainException::class)
        ->and(fn () => app(RetireAnimalIdentifier::class)($identifier->fresh(), 'again'))->toThrow(DomainException::class);
});

it('looks animals up by number, public id, QR payload URL and identifier', function () {
    $animal = register();
    $lookup = app(LookupAnimal::class);

    expect($lookup($animal->animal_number)->is($animal))->toBeTrue()
        ->and($lookup(strtolower($animal->public_id))->is($animal))->toBeTrue()
        ->and($lookup($animal->qrPayload())->is($animal))->toBeTrue()
        ->and($lookup('NOPE-1'))->toBeNull()
        ->and($lookup('  '))->toBeNull();
});

it('protects the permanent number and forbids deleting animals', function () {
    $animal = register();

    expect(fn () => $animal->update(['animal_number' => 'IPA-SOW-9999']))->toThrow(LogicException::class)
        ->and(fn () => $animal->update(['public_id' => 'X']))->toThrow(LogicException::class)
        ->and(fn () => $animal->delete())->toThrow(LogicException::class);
});

it('preserves every movement and keeps the current position in step', function () {
    $a = newPen('PA');
    $b = newPen('PB');
    $move = app(RecordAnimalMovement::class);
    $animal = register(['pen_id' => $a->id]);

    $move($animal, $b->id, null, now()->addMinute(), notes: 'Weaned');

    $animal->refresh();
    expect($animal->currentPen->is($b))->toBeTrue()
        ->and($animal->movements)->toHaveCount(2)
        ->and($animal->movements->first()->fromPen->is($a))->toBeTrue()
        ->and(fn () => AnimalMovement::first()->update(['notes' => 'edited']))->toThrow(LogicException::class)
        ->and(fn () => AnimalMovement::first()->delete())->toThrow(LogicException::class);
});

it('can place animals in non-pen locations and rejects ambiguous destinations', function () {
    $animal = register();
    $location = Location::create([
        'production_unit_id' => ProductionUnit::firstWhere('code', 'PIG')->id, 'code' => 'QA',
        'type_id' => LookupValue::where('category', LookupCategory::LocationType->value)->value('id'), 'name' => 'Quarantine area',
    ]);
    $move = app(RecordAnimalMovement::class);

    $move($animal, null, $location->id);
    expect($animal->fresh()->currentLocation->is($location))->toBeTrue()
        ->and($animal->fresh()->current_pen_id)->toBeNull()
        ->and(fn () => $move($animal, newPen()->id, $location->id))->toThrow(DomainException::class, 'either a pen or a location')
        ->and(fn () => $move($animal, null, null))->toThrow(DomainException::class);
});

it('enforces pen capacity, inactive pens and pointless moves', function () {
    $small = newPen('SMALL', 1);
    $inactive = newPen('OFF');
    $inactive->update(['is_active' => false]);
    $move = app(RecordAnimalMovement::class);
    $one = register(['pen_id' => $small->id]);
    $two = register();

    expect(fn () => $move($two, $small->id))->toThrow(DomainException::class, 'is full')
        ->and(fn () => $move($two, $inactive->id))->toThrow(DomainException::class, 'inactive')
        ->and(fn () => $move($one, $small->id))->toThrow(DomainException::class, 'already there');
});

it('rejects movements dated in the future or before the latest recorded one', function () {
    $animal = register(['pen_id' => newPen('PA')->id]);
    $other = newPen('PB');
    $move = app(RecordAnimalMovement::class);

    expect(fn () => $move($animal, $other->id, null, now()->addDay()))->toThrow(DomainException::class, 'future')
        ->and(fn () => $move($animal, $other->id, null, now()->subDay()))->toThrow(DomainException::class, 'earlier than');
});

it('is idempotent for retried movements and weights', function () {
    $animal = register(['pen_id' => newPen('PA')->id]);
    $other = newPen('PB');
    $move = app(RecordAnimalMovement::class);
    $weigh = app(RecordWeight::class);

    $first = $move($animal, $other->id, null, now()->addMinute(), idempotencyKey: 'dev1-0001');
    $again = $move($animal, $other->id, null, now()->addMinute(), idempotencyKey: 'dev1-0001');
    $w1 = $weigh($animal, '25.5', idempotencyKey: 'dev1-0002');
    $w2 = $weigh($animal, '25.5', idempotencyKey: 'dev1-0002');

    expect($again->is($first))->toBeTrue()
        ->and($animal->movements()->count())->toBe(2)
        ->and($w2->is($w1))->toBeTrue()
        ->and($animal->weights()->count())->toBe(1)
        ->and(fn () => $move(register(), $other->id, null, null, idempotencyKey: 'dev1-0001'))->toThrow(DomainException::class, 'different animal');
});

it('replays a weight retry only for the same animal', function () {
    $animal = register();
    $other = register();
    $weigh = app(RecordWeight::class);

    $first = $weigh($animal, '10', idempotencyKey: 'dev9-0001');

    expect($weigh($animal, '10', idempotencyKey: 'dev9-0001')->is($first))->toBeTrue()
        ->and(fn () => $weigh($other, '10', idempotencyKey: 'dev9-0001'))->toThrow(DomainException::class, 'different animal')
        ->and($other->weights()->count())->toBe(0);
});

it('replays a movement retry even after the animal has moved on', function () {
    $animal = register(['pen_id' => newPen('PA')->id]);
    $pen = newPen('PB');
    $move = app(RecordAnimalMovement::class);

    $first = $move($animal, $pen->id, null, now()->addMinute(), idempotencyKey: 'dev9-0002');
    $move($animal, newPen('PC')->id, null, now()->addMinutes(2));
    $count = $animal->movements()->count();

    expect($move($animal, $pen->id, null, now()->addMinute(), idempotencyKey: 'dev9-0002')->is($first))->toBeTrue()
        ->and($animal->movements()->count())->toBe($count);
});

it('records weights with validation, and voids rather than edits them', function () {
    $animal = register(['birth_date' => now()->subDays(30)->toDateString()]);
    $weigh = app(RecordWeight::class);

    $weight = $weigh($animal, '12.50', now()->subDay());
    expect($weight->weight_kg)->toBe('12.50')
        ->and($animal->latestWeight()->is($weight))->toBeTrue()
        ->and(fn () => $weigh($animal, '0'))->toThrow(DomainException::class, 'positive')
        ->and(fn () => $weigh($animal, '-3'))->toThrow(DomainException::class, 'positive')
        ->and(fn () => $weigh($animal, '12.345'))->toThrow(DomainException::class, 'positive')
        ->and(fn () => $weigh($animal, 'abc'))->toThrow(DomainException::class, 'positive')
        ->and(fn () => $weigh($animal, '9999'))->toThrow(DomainException::class, 'plausible')
        ->and(fn () => $weigh($animal, '10', now()->addDay()))->toThrow(DomainException::class, 'future')
        ->and(fn () => $weigh($animal, '10', now()->subDays(60)))->toThrow(DomainException::class, 'before the animal was born')
        ->and(fn () => $weight->update(['weight_kg' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $weight->delete())->toThrow(LogicException::class);

    $newer = $weigh($animal, '14.00');
    app(VoidWeight::class)($newer, 'Scale not zeroed');

    expect($animal->latestWeight()->is($weight))->toBeTrue()
        ->and($newer->fresh()->isVoided())->toBeTrue()
        ->and(fn () => app(VoidWeight::class)($newer->fresh(), 'again'))->toThrow(DomainException::class)
        ->and(fn () => app(VoidWeight::class)($weight, ' '))->toThrow(DomainException::class, 'reason');
});

it('honours the configurable maximum weight', function () {
    app(ResolveSettings::class)->set('animals.max_weight_kg', 50);
    $animal = register();

    expect(fn () => app(RecordWeight::class)($animal, '60'))->toThrow(DomainException::class, 'plausible')
        ->and(app(RecordWeight::class)($animal, '49.99')->weight_kg)->toBe('49.99');
});

it('validates parentage: sex, self, dates and cycles', function () {
    $set = app(SetAnimalParentage::class);
    $boar = register(['sex' => 'male', 'category_id' => categoryId('boar')]);
    $sow = register();
    $gilt = register(['category_id' => categoryId('gilt')]);

    expect($set($gilt, $boar->id, $sow->id)->dam->is($sow))->toBeTrue()
        ->and(fn () => $set($gilt, $sow->id, null))->toThrow(DomainException::class, 'must be male')
        ->and(fn () => $set($gilt, null, $boar->id))->toThrow(DomainException::class, 'must be female')
        ->and(fn () => $set($sow, null, $sow->id))->toThrow(DomainException::class, 'own parent')
        ->and(fn () => $set($sow, null, $gilt->id))->toThrow(DomainException::class, 'descends from')
        ->and(fn () => $set($gilt, null, null, 'Unknown boar from ABC farm')->sire_note)->not->toThrow(DomainException::class);

    $older = register(['birth_date' => now()->subDays(5)->toDateString()]);
    $younger = register(['birth_date' => now()->subDays(50)->toDateString(), 'category_id' => categoryId('gilt')]);
    expect(fn () => $set($younger, null, $older->id))->toThrow(DomainException::class, 'born before');
});

it('changes status to a final state, exits the pen, and keeps the trail', function () {
    Event::fake([AnimalMoved::class]);
    $pen = newPen();
    $animal = register(['pen_id' => $pen->id]);
    $change = app(ChangeAnimalStatus::class);

    $history = $change($animal, AnimalStatus::Sold, 'Sold to a customer', now()->addMinute());

    $animal->refresh();
    expect($animal->status)->toBe(AnimalStatus::Sold)
        ->and($animal->current_pen_id)->toBeNull()
        ->and($animal->movements->first()->toLabel())->toBe('Left the farm')
        ->and($history->from_status)->toBe(AnimalStatus::Active)
        ->and($animal->statusHistory)->toHaveCount(2);
});

it('treats terminal statuses as final', function () {
    $animal = register(['pen_id' => newPen()->id]);
    $change = app(ChangeAnimalStatus::class);
    $change($animal, AnimalStatus::Dead, 'Crushed by sow', now()->addMinute());

    expect(fn () => $change($animal, AnimalStatus::Sold, 'oops'))->toThrow(DomainException::class, 'already Dead')
        ->and(fn () => app(RecordAnimalMovement::class)($animal, newPen('P2')->id))->toThrow(DomainException::class, 'cannot be moved')
        ->and(fn () => app(RecordWeight::class)($animal, '20'))->toThrow(DomainException::class, 'no longer')
        ->and(fn () => app(AddAnimalIdentifier::class)($animal, IdentifierType::Manual, 'LATE'))->toThrow(DomainException::class)
        ->and(fn () => $change(register(), AnimalStatus::Active, 'x'))->toThrow(DomainException::class, 'final status')
        ->and(fn () => $change(register(), AnimalStatus::Culled, ' '))->toThrow(DomainException::class, 'reason');
});

it('builds a chronological lifecycle history', function () {
    $animal = register(['pen_id' => newPen('PA')->id]);
    app(RecordWeight::class)($animal, '30', now()->addMinutes(1));
    app(RecordAnimalMovement::class)($animal, newPen('PB')->id, null, now()->addMinutes(2));
    app(ChangeAnimalStatus::class)($animal, AnimalStatus::Culled, 'Poor performance', now()->addMinutes(3));

    $types = app(GetAnimalHistory::class)($animal)->pluck('type');

    expect($types->first())->toBe('Status')
        ->and($types->unique()->sort()->values()->all())->toBe(['Movement', 'Status', 'Weight'])
        ->and($types->count())->toBe(6);
});

it('makes a pen undeletable once animals have used it', function () {
    $pen = newPen('USED');
    expect($pen->isInUse())->toBeFalse();

    register(['pen_id' => $pen->id]);

    expect($pen->fresh()->isInUse())->toBeTrue()
        ->and(fn () => DB::table('pens')->where('id', $pen->id)->delete())->toThrow(QueryException::class);
});

it('uses a factory for lightweight test animals', function () {
    $animal = Animal::factory()->create();

    expect($animal->status)->toBe(AnimalStatus::Active)->and($animal->sex)->toBe(AnimalSex::Female);
});
