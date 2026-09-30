<?php

use App\Domain\Biosecurity\Actions\RecordBiosecurityCheck;
use App\Domain\Biosecurity\Actions\RecordVisitorArrival;
use App\Domain\Biosecurity\Actions\RecordVisitorDeparture;
use App\Domain\Biosecurity\Models\BiosecurityCheck;
use App\Domain\Biosecurity\Models\BiosecurityChecklistItem;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\System\Exceptions\DomainException;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

function visitor(array $overrides = []): array
{
    return array_merge([
        'visitor_name' => 'Chidi Okafor', 'purpose' => 'Feed delivery', 'organisation' => 'AgroCo',
        'vehicle_registration' => 'ABC-123-XY', 'last_pig_contact_hours' => 72, 'health_declaration' => true,
    ], $overrides);
}

function checklistItem(string $code, string $text = 'Footbath in place'): BiosecurityChecklistItem
{
    return BiosecurityChecklistItem::create(['code' => $code, 'description' => $text]);
}

it('lets a compliant visitor in without approval and retains the record', function () {
    $visit = app(RecordVisitorArrival::class)(visitor());

    expect($visit->approved_by)->toBeNull()
        ->and($visit->health_declaration)->toBeTrue()
        ->and($visit->arrived_at->isToday())->toBeTrue()
        ->and($visit->departed_at)->toBeNull()
        ->and(fn () => $visit->update(['visitor_name' => 'Someone else']))->toThrow(LogicException::class)
        ->and(fn () => $visit->delete())->toThrow(LogicException::class);
});

it('requires a manager\'s approval for visitors who have not met the entry conditions', function (array $override) {
    $arrive = app(RecordVisitorArrival::class);
    $farmWorker = farmWorker();
    $manager = userWithRole('Farm Manager');

    expect(fn () => $arrive(visitor($override)))->toThrow(DomainException::class, 'needs approval')
        ->and(fn () => $arrive(visitor($override), $farmWorker))->toThrow(DomainException::class, 'needs approval');

    $visit = $arrive(visitor($override), $manager);

    expect($visit->approved_by)->toBe($manager->id);
})->with([
    'recent pig contact' => [['last_pig_contact_hours' => 12]],
    'unknown pig contact' => [['last_pig_contact_hours' => null]],
    'no health declaration' => [['health_declaration' => false]],
]);

it('uses the configured pig-free period', function () {
    app(ResolveSettings::class)->set('biosecurity.min_pig_contact_free_hours', 96);

    expect(fn () => app(RecordVisitorArrival::class)(visitor(['last_pig_contact_hours' => 72])))->toThrow(DomainException::class, 'needs approval')
        ->and(app(RecordVisitorArrival::class)(visitor(['last_pig_contact_hours' => 96]))->exists)->toBeTrue();
});

it('validates visitor arrivals', function () {
    $arrive = app(RecordVisitorArrival::class);

    expect(fn () => $arrive(visitor(['visitor_name' => ' '])))->toThrow(DomainException::class, 'name')
        ->and(fn () => $arrive(visitor(['purpose' => ''])))->toThrow(DomainException::class, 'purpose')
        ->and(fn () => $arrive(visitor(['arrived_at' => now()->addHour()])))->toThrow(DomainException::class, 'future');
});

it('signs visitors out once, after they arrived', function () {
    $visit = app(RecordVisitorArrival::class)(visitor(['arrived_at' => now()->subHours(2)]));
    $out = app(RecordVisitorDeparture::class);

    expect(fn () => $out($visit, now()->subHours(3)))->toThrow(DomainException::class, 'between the arrival')
        ->and(fn () => $out($visit, now()->addHours(2)))->toThrow(DomainException::class, 'between the arrival');

    $out($visit, now()->subHour());

    expect($visit->fresh()->departed_at)->not->toBeNull()
        ->and(fn () => $out($visit->fresh()))->toThrow(DomainException::class, 'already been signed out');
});

it('scores an inspection and keeps the checklist wording it was answered against', function () {
    $a = checklistItem('FOOTBATH', 'Footbath in place');
    $b = checklistItem('FENCE', 'Perimeter fence intact');
    $c = checklistItem('LOG', 'Visitor log up to date');
    $unit = ProductionUnit::firstWhere('code', 'PIG');

    $check = app(RecordBiosecurityCheck::class)(now(), [
        ['item_id' => $a->id, 'passed' => true],
        ['item_id' => $b->id, 'passed' => false, 'notes' => 'Gap near the gate'],
        ['item_id' => $c->id, 'passed' => true],
    ], $unit->id, 'Monthly check');

    $a->update(['description' => 'Footbath with fresh disinfectant']);

    expect($check->items_total)->toBe(3)
        ->and($check->items_passed)->toBe(2)
        ->and($check->scorePercent())->toBe('66.67')
        ->and($check->productionUnit->is($unit))->toBeTrue()
        ->and($check->items->pluck('description')->all())->toContain('Footbath in place')->not->toContain('Footbath with fresh disinfectant')
        ->and($check->items->firstWhere('passed', false)->notes)->toBe('Gap near the gate')
        ->and(fn () => $check->update(['notes' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $check->items->first()->update(['passed' => false]))->toThrow(LogicException::class)
        ->and(owner()->can('delete', $a->fresh()))->toBeFalse();
});

it('validates inspections', function () {
    $active = checklistItem('ONE');
    $inactive = checklistItem('TWO');
    $inactive->update(['is_active' => false]);
    $check = app(RecordBiosecurityCheck::class);

    expect(fn () => $check(now(), []))->toThrow(DomainException::class, 'at least one')
        ->and(fn () => $check(now()->addDay(), [['item_id' => $active->id, 'passed' => true]]))->toThrow(DomainException::class, 'future')
        ->and(fn () => $check(now(), [['item_id' => $inactive->id, 'passed' => true]]))->toThrow(DomainException::class, 'active checklist item')
        ->and(fn () => $check(now(), [['item_id' => $active->id, 'passed' => true], ['item_id' => $active->id, 'passed' => false]]))->toThrow(DomainException::class, 'once each')
        ->and(BiosecurityCheck::count())->toBe(0);
});

it('applies biosecurity permission defaults', function () {
    $visit = app(RecordVisitorArrival::class)(visitor());

    expect(farmWorker()->can('create', $visit::class))->toBeTrue()
        ->and(farmWorker()->can('approve', $visit::class))->toBeFalse()
        ->and(userWithRole('Veterinarian')->can('approve', $visit::class))->toBeTrue()
        ->and(userWithRole('Sales Officer')->can('viewAny', $visit::class))->toBeFalse()
        ->and(userWithRole('Feed Mill Manager')->can('viewAny', $visit::class))->toBeFalse()
        ->and(owner()->can('delete', $visit))->toBeFalse();
});
