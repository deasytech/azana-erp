<?php

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Actions\GetBreedingCalendar;
use App\Domain\Breeding\Actions\GetSowStatus;
use App\Domain\Breeding\Actions\RecordAbortion;
use App\Domain\Breeding\Actions\RecordHeat;
use App\Domain\Breeding\Actions\RecordPregnancyCheck;
use App\Domain\Breeding\Actions\RecordService;
use App\Domain\Breeding\Events\FarrowingRecorded;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Breed;
use App\Domain\Litter\Actions\GetLitterKpis;
use App\Domain\Litter\Actions\GetSowPerformance;
use App\Domain\Litter\Actions\RecordLitterLoss;
use App\Domain\Litter\Actions\RegisterLitterPiglets;
use App\Domain\Litter\Actions\WeanLitter;
use App\Domain\Litter\Models\Litter;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\PregnancyCheckResult;
use App\Enums\ReproductiveStatus;
use App\Enums\ServiceMethod;
use App\Enums\ServiceOutcome;
use App\Models\AuditLog;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

it('calculates expected dates from the farm settings at service time', function () {
    $sow = register();
    $on = now()->subDays(10)->startOfDay();

    $service = app(RecordService::class)($sow, ServiceMethod::Natural, $on, boar()->id);

    expect($service->expected_pregnancy_check_on->toDateString())->toBe($on->copy()->addDays(28)->toDateString())
        ->and($service->expected_farrowing_on->toDateString())->toBe($on->copy()->addDays(114)->toDateString())
        ->and($service->expected_weaning_on->toDateString())->toBe($on->copy()->addDays(114 + 28)->toDateString())
        ->and($service->expected_next_heat_on->toDateString())->toBe($on->copy()->addDays(21)->toDateString())
        ->and($service->expected_next_service_on->toDateString())->toBe($on->copy()->addDays(114 + 28 + 5)->toDateString())
        ->and($service->outcome)->toBe(ServiceOutcome::Pending);
});

it('uses changed settings for new services without rewriting earlier ones', function () {
    $sow = register();
    $other = register();
    $first = serve($sow, 5);

    app(ResolveSettings::class)->set('breeding.gestation_days', 116);
    $second = serve($other, 5);

    expect($first->fresh()->expected_farrowing_on->toDateString())->toBe(now()->subDays(5)->addDays(114)->toDateString())
        ->and($second->expected_farrowing_on->toDateString())->toBe(now()->subDays(5)->addDays(116)->toDateString());
});

it('validates who can be served and by what', function () {
    $sow = register();
    $on = now()->subDay()->startOfDay();
    $service = app(RecordService::class);

    expect(fn () => $service($sow, ServiceMethod::Natural, $on))->toThrow(DomainException::class, 'needs the boar')
        ->and(fn () => $service($sow, ServiceMethod::ArtificialInsemination, $on))->toThrow(DomainException::class, 'semen source')
        ->and(fn () => $service($sow, ServiceMethod::Natural, $on, register()->id))->toThrow(DomainException::class, 'active boar')
        ->and(fn () => $service(boar(), ServiceMethod::Natural, $on, boar()->id))->toThrow(DomainException::class, 'sow or gilt')
        ->and(fn () => $service($sow, ServiceMethod::Natural, now()->addDay(), boar()->id))->toThrow(DomainException::class, 'future')
        ->and($service($sow, ServiceMethod::ArtificialInsemination, $on, null, ' Boar X, ABC Genetics ', technicianName: 'Ada')->semen_source)->toBe('Boar X, ABC Genetics');
});

it('enforces the minimum age at first service', function () {
    $young = register(['category_id' => categoryId('gilt'), 'birth_date' => now()->subDays(100)->toDateString()]);
    $old = register(['category_id' => categoryId('gilt'), 'birth_date' => now()->subDays(300)->toDateString()]);

    expect(fn () => serve($young, 1))->toThrow(DomainException::class, 'minimum first-service age')
        ->and(serve($old, 1)->exists)->toBeTrue();
});

it('blocks serving a pregnant or lactating sow and out-of-order services', function () {
    $sow = register();
    $first = serve($sow, 20);

    expect(fn () => serve($sow, 30))->toThrow(DomainException::class, 'earlier than');

    app(RecordPregnancyCheck::class)($first, PregnancyCheckResult::Positive, now()->subDays(5)->startOfDay());
    expect(fn () => serve($sow, 1))->toThrow(DomainException::class, 'confirmed pregnant');

    $lactating = register();
    serve($lactating, 114);
    farrow($lactating);
    expect(fn () => serve($lactating, 0))->toThrow(DomainException::class, 'unweaned litter');
});

it('applies a pregnancy result to a double mating but not to an older cycle', function () {
    $sow = register();
    $a = serve($sow, 40);
    $b = serve($sow, 38, $a->boar); // within the 3-day window of $a
    $check = app(RecordPregnancyCheck::class);

    $check($a->fresh(), PregnancyCheckResult::Negative, now()->subDays(10)->startOfDay());
    expect($a->fresh()->outcome)->toBe(ServiceOutcome::NotPregnant)
        ->and($b->fresh()->outcome)->toBe(ServiceOutcome::NotPregnant);

    $later = serve($sow, 5);
    $check($later, PregnancyCheckResult::Positive, now()->startOfDay());
    expect($later->fresh()->outcome)->toBe(ServiceOutcome::Pregnant)
        ->and($a->fresh()->outcome)->toBe(ServiceOutcome::NotPregnant)
        ->and(fn () => $check($a->fresh(), PregnancyCheckResult::Positive, now()->startOfDay()))->toThrow(DomainException::class, 'already');
});

it('validates pregnancy check dates', function () {
    $service = serve(register(), 10);

    expect(fn () => app(RecordPregnancyCheck::class)($service, PregnancyCheckResult::Positive, now()->subDays(20)))->toThrow(DomainException::class, 'between the service date')
        ->and(fn () => app(RecordPregnancyCheck::class)($service, PregnancyCheckResult::Positive, now()->addDay()))->toThrow(DomainException::class, 'between the service date');
});

it('derives the reproductive status through the whole cycle', function () {
    $sow = register();
    $status = fn () => app(GetSowStatus::class)($sow->fresh());

    expect($status())->toBe(ReproductiveStatus::Open);

    $service = serve($sow, 114);
    expect($status())->toBe(ReproductiveStatus::Served);

    app(RecordPregnancyCheck::class)($service, PregnancyCheckResult::Positive, now()->subDays(80)->startOfDay());
    expect($status())->toBe(ReproductiveStatus::Pregnant);

    $litter = farrow($sow);
    expect($status())->toBe(ReproductiveStatus::Lactating);

    app(WeanLitter::class)($litter, now()->startOfDay(), 10, '70');
    expect($status())->toBe(ReproductiveStatus::Open)
        ->and(app(GetSowStatus::class)(boar()))->toBeNull();
});

it('records an abortion with a reason and returns the sow to open', function () {
    $sow = register();
    $service = serve($sow, 50);
    app(RecordPregnancyCheck::class)($service, PregnancyCheckResult::Positive, now()->subDays(20)->startOfDay());

    expect(fn () => app(RecordAbortion::class)($service, now()->startOfDay(), ' '))->toThrow(DomainException::class, 'reason');

    app(RecordAbortion::class)($service, now()->startOfDay(), 'Suspected infection');

    expect($service->fresh()->outcome)->toBe(ServiceOutcome::Aborted)
        ->and(app(GetSowStatus::class)($sow->fresh()))->toBe(ReproductiveStatus::Open)
        ->and(AuditLog::where('event', 'abortion_recorded')->value('reason'))->toBe('Suspected infection')
        ->and(fn () => app(RecordAbortion::class)($service->fresh(), now()->startOfDay(), 'again'))->toThrow(DomainException::class, 'already');
});

it('records heat, once per day and only for breeding females', function () {
    $sow = register();

    expect(app(RecordHeat::class)($sow, now()->startOfDay(), 'Standing heat')->detected_on->isToday())->toBeTrue()
        ->and(fn () => app(RecordHeat::class)($sow, now()->startOfDay()))->toThrow(DomainException::class, 'already recorded')
        ->and(fn () => app(RecordHeat::class)($sow, now()->addDay()))->toThrow(DomainException::class, 'future')
        ->and(fn () => app(RecordHeat::class)(boar(), now()->startOfDay()))->toThrow(DomainException::class, 'sow or gilt');
});

it('creates a litter automatically at farrowing and links service, sire and sow', function () {
    Event::fake([FarrowingRecorded::class]);
    $sow = register();
    $boar = boar();
    $service = serve($sow, 114, $boar);
    app(RecordPregnancyCheck::class)($service, PregnancyCheckResult::Positive, now()->subDays(80)->startOfDay());

    $litter = farrow($sow, ['total_birth_weight_kg' => '15.00', 'assisted' => true]);

    expect($litter->litter_number)->toBe('IPA-'.now()->year.'-SOW0001-L01')
        ->and($litter->sow->is($sow))->toBeTrue()
        ->and($litter->sire->is($boar))->toBeTrue()
        ->and($litter->service->is($service))->toBeTrue()
        ->and($litter->farrowing->born_alive)->toBe(10)
        ->and($litter->expected_weaning_on->toDateString())->toBe(now()->addDays(28)->toDateString())
        ->and($service->fresh()->outcome)->toBe(ServiceOutcome::Farrowed);

    Event::assertDispatched(FarrowingRecorded::class);
});

it('numbers each sow\'s litters L01, L02...', function () {
    $sow = register();
    $first = farrow($sow, ['farrowed_on' => now()->subDays(200)->startOfDay()]);
    app(WeanLitter::class)($first, now()->subDays(170)->startOfDay(), 9, '60');
    $second = farrow($sow);

    expect($first->litter_number)->toEndWith('-L01')->and($second->litter_number)->toEndWith('-L02');
});

it('promotes a gilt to a sow at her first farrowing, keeping her number', function () {
    $gilt = register(['category_id' => categoryId('gilt')]);
    $number = $gilt->animal_number;

    farrow($gilt);

    expect($gilt->fresh()->category->code)->toBe('sow')->and($gilt->fresh()->animal_number)->toBe($number);
});

it('validates farrowing input', function (array $bad, string $message) {
    expect(fn () => farrow(register(), $bad))->toThrow(DomainException::class, $message);
})->with([
    'counts do not add up' => [['total_born' => 12, 'born_alive' => 9], 'must equal the total born'],
    'nothing born' => [['total_born' => 0, 'born_alive' => 0, 'stillborn' => 0, 'mummified' => 0], 'at least 1'],
    'future date' => [['farrowed_on' => now()->addDay()], 'between the sow'],
    'bad birth weight' => [['total_birth_weight_kg' => '0'], 'positive'],
]);

it('blocks a second farrowing while the first litter is unweaned and on the same day', function () {
    $sow = register();
    $litter = farrow($sow, ['farrowed_on' => now()->subDays(10)->startOfDay()]);

    expect(fn () => farrow($sow))->toThrow(DomainException::class, 'unweaned litter');

    app(WeanLitter::class)($litter, now()->subDays(5)->startOfDay(), 10, '50');
    expect(fn () => farrow($sow, ['farrowed_on' => now()->subDays(10)->startOfDay()]))->toThrow(DomainException::class, 'already recorded');
});

it('picks the right service for a farrowing, or rejects a wrong one', function () {
    $sow = register();
    $old = serve($sow, 200);
    app(RecordPregnancyCheck::class)($old, PregnancyCheckResult::Negative, now()->subDays(170)->startOfDay());
    $current = serve($sow, 114);
    $stranger = serve(register(), 114);

    expect(fn () => farrow($sow, ['breeding_service_id' => $stranger->id]))->toThrow(DomainException::class, 'does not belong')
        ->and(fn () => farrow($sow, ['breeding_service_id' => $old->id]))->toThrow(DomainException::class, 'cannot have produced')
        ->and(farrow($sow)->service->is($current))->toBeTrue();
});

it('allows a farrowing with no recorded service (e.g. a bought-in pregnant sow)', function () {
    $litter = farrow(register());

    expect($litter->service)->toBeNull()->and($litter->sire)->toBeNull();
});

it('replays a retried farrowing instead of duplicating it', function () {
    $sow = register();
    $first = farrow($sow, ['idempotency_key' => 'dev2-0001']);

    expect(farrow($sow, ['idempotency_key' => 'dev2-0001'])->is($first))->toBeTrue()
        ->and(Litter::count())->toBe(1)
        ->and(fn () => farrow(register(), ['idempotency_key' => 'dev2-0001']))->toThrow(DomainException::class, 'different sow');
});

it('replays a retried service', function () {
    $sow = register();
    $boar = boar();
    $do = fn () => app(RecordService::class)($sow, ServiceMethod::Natural, now()->subDay()->startOfDay(), $boar->id, idempotencyKey: 'dev2-0002');

    expect($do()->is($do()))->toBeTrue()->and(BreedingService::count())->toBe(1);
});

it('registers piglets as animals linked to litter, dam and sire with birth weights', function () {
    $sow = register(['breed_id' => Breed::firstWhere('code', 'LW')->id]);
    $boar = boar();
    $service = serve($sow, 114, $boar);
    $litter = farrow($sow);

    $piglets = app(RegisterLitterPiglets::class)($litter, [
        ['sex' => 'female', 'birth_weight_kg' => '1.45', 'identifiers' => [['type' => 'ear_tag', 'value' => 'p-1']]],
        ['sex' => 'male', 'birth_weight_kg' => '1.60'],
    ]);

    $first = $piglets->first()->fresh();
    expect($piglets)->toHaveCount(2)
        ->and($first->category->code)->toBe('piglet')
        ->and($first->animal_number)->toBe('IPA-PIGLET-0001')
        ->and($first->breed_id)->toBe($sow->breed_id)
        ->and($first->birth_date->toDateString())->toBe(now()->toDateString())
        ->and($first->parentage->dam->is($sow))->toBeTrue()
        ->and($first->parentage->sire->is($service->boar))->toBeTrue()
        ->and($first->parentage->litter->is($litter))->toBeTrue()
        ->and($first->identifiers->first()->value)->toBe('P-1')
        ->and($first->weights->first()->weight_kg)->toBe('1.45')
        ->and($litter->piglets()->count())->toBe(2);
});

it('will not register more piglets than were born alive, or for a weaned litter', function () {
    $litter = farrow(register(), ['total_born' => 3, 'born_alive' => 2, 'stillborn' => 1, 'mummified' => 0]);
    $add = app(RegisterLitterPiglets::class);

    $add($litter, [['sex' => 'male'], ['sex' => 'female']]);

    expect(fn () => $add($litter, [['sex' => 'male']]))->toThrow(DomainException::class, '0 born-alive')
        ->and(fn () => $add($litter, []))->toThrow(DomainException::class);

    app(WeanLitter::class)($litter, now()->startOfDay(), 2, '14');
    expect(fn () => $add($litter, [['sex' => 'male']]))->toThrow(DomainException::class, 'already weaned');
});

it('rolls the whole piglet registration back if one piglet is invalid', function () {
    $litter = farrow(register());

    expect(fn () => app(RegisterLitterPiglets::class)($litter, [['sex' => 'male'], ['sex' => 'female', 'birth_weight_kg' => '-1']]))
        ->toThrow(DomainException::class);

    expect($litter->piglets()->count())->toBe(0)->and(Animal::where('birth_date', now()->toDateString())->count())->toBe(0);
});

it('records pre-weaning losses within the born-alive count', function () {
    $litter = farrow(register());
    $loss = app(RecordLitterLoss::class);

    $loss($litter, 2, now()->startOfDay(), 'Crushed');

    expect($litter->losses()->sum('count'))->toBe(2)
        ->and(fn () => $loss($litter, 9, now()->startOfDay()))->toThrow(DomainException::class, 'exceed')
        ->and(fn () => $loss($litter, 0, now()->startOfDay()))->toThrow(DomainException::class, 'at least one')
        ->and(fn () => $loss($litter, 1, now()->addDay()))->toThrow(DomainException::class, 'between')
        ->and(fn () => $loss($litter, 1, now()->subDays(3)))->toThrow(DomainException::class, 'between');
});

it('marks a tracked piglet dead when its loss is recorded', function () {
    $litter = farrow(register());
    $piglet = app(RegisterLitterPiglets::class)($litter, [['sex' => 'male']])->first();

    app(RecordLitterLoss::class)($litter, 1, now()->startOfDay(), 'Scours', $piglet->id);

    expect($piglet->fresh()->status)->toBe(AnimalStatus::Dead)
        ->and($litter->losses()->first()->animal_id)->toBe($piglet->id)
        ->and(fn () => app(RecordLitterLoss::class)($litter, 1, now()->startOfDay(), null, $piglet->id))->toThrow(DomainException::class)
        ->and(fn () => app(RecordLitterLoss::class)($litter, 1, now()->startOfDay(), null, register()->id))->toThrow(DomainException::class, 'not a piglet');
});

it('weans a litter: closes it, updates tracked piglets and dates the next service', function () {
    $sow = register();
    $litter = farrow($sow, ['farrowed_on' => now()->subDays(28)->startOfDay()]);
    $piglets = app(RegisterLitterPiglets::class)($litter, [['sex' => 'male'], ['sex' => 'female']]);
    $dead = $piglets->last();
    app(RecordLitterLoss::class)($litter, 1, now()->subDays(20)->startOfDay(), 'Crushed', $dead->id);
    $pen = newPen('NURSERY');

    $record = app(WeanLitter::class)($litter, now()->startOfDay(), 9, '63.00', $pen->id);

    expect($litter->fresh()->status->value)->toBe('weaned')
        ->and($litter->fresh()->weaned_on->isToday())->toBeTrue()
        ->and($record->expected_next_service_on->toDateString())->toBe(now()->addDays(5)->toDateString())
        ->and($piglets->first()->fresh()->category->code)->toBe('weaner')
        ->and($piglets->first()->fresh()->currentPen->is($pen))->toBeTrue()
        ->and($dead->fresh()->category->code)->toBe('piglet')
        ->and($dead->fresh()->status)->toBe(AnimalStatus::Dead);
});

it('validates weaning', function () {
    $litter = farrow(register(), ['farrowed_on' => now()->subDays(28)->startOfDay()]);
    app(RecordLitterLoss::class)($litter, 2, now()->subDays(20)->startOfDay());
    $wean = app(WeanLitter::class);

    expect(fn () => $wean($litter, now()->startOfDay(), 9, '50'))->toThrow(DomainException::class, 'between 0 and 8')
        ->and(fn () => $wean($litter, now()->startOfDay(), -1))->toThrow(DomainException::class, 'between 0 and 8')
        ->and(fn () => $wean($litter, now()->addDay(), 8))->toThrow(DomainException::class, 'weaning date')
        ->and(fn () => $wean($litter, now()->subDays(40), 8))->toThrow(DomainException::class, 'weaning date')
        ->and(fn () => $wean($litter, now()->startOfDay(), 8, '0'))->toThrow(DomainException::class, 'positive')
        ->and(fn () => $wean($litter, now()->startOfDay(), 0, '10'))->toThrow(DomainException::class, 'positive');

    $wean($litter, now()->startOfDay(), 8, '56');
    expect(fn () => $wean($litter->fresh(), now()->startOfDay(), 8, '56'))->toThrow(DomainException::class, 'already weaned');
});

it('lets a litter be weaned with nobody left', function () {
    $litter = farrow(register(), ['total_born' => 2, 'born_alive' => 2, 'stillborn' => 0, 'mummified' => 0]);
    app(RecordLitterLoss::class)($litter, 2, now()->startOfDay());

    $record = app(WeanLitter::class)($litter, now()->startOfDay(), 0);

    expect($record->weaned_count)->toBe(0)->and(app(GetLitterKpis::class)($litter->fresh())['weaning_percent'])->toBe('0.00');
});

it('calculates litter KPIs exactly', function () {
    $sow = register();
    $service = serve($sow, 142);
    $litter = farrow($sow, ['farrowed_on' => now()->subDays(28)->startOfDay(), 'total_birth_weight_kg' => '15.00']);
    app(RecordLitterLoss::class)($litter, 2, now()->subDays(20)->startOfDay(), 'Crushed');

    $before = app(GetLitterKpis::class)($litter);
    expect($before['weaned'])->toBeNull()->and($before['weaning_percent'])->toBeNull()->and($before['lactation_days'])->toBeNull();

    app(WeanLitter::class)($litter, now()->startOfDay(), 8, '56.00');
    $kpi = app(GetLitterKpis::class)($litter->fresh());

    expect($kpi)->toMatchArray([
        'total_born' => 12, 'born_alive' => 10, 'stillborn' => 1, 'mummified' => 1,
        'stillborn_percent' => '8.33',
        'pre_weaning_losses' => 2, 'pre_weaning_mortality_percent' => '20.00',
        'weaned' => 8, 'weaning_percent' => '80.00',
        'avg_birth_weight_kg' => '1.50', 'avg_weaning_weight_kg' => '7.00',
        'gestation_days' => 114, 'lactation_days' => 28,
    ]);
});

it('falls back to tracked piglets for average birth weight', function () {
    $litter = farrow(register());
    app(RegisterLitterPiglets::class)($litter, [['sex' => 'male', 'birth_weight_kg' => '1.20'], ['sex' => 'female', 'birth_weight_kg' => '1.55']]);

    expect(app(GetLitterKpis::class)($litter)['avg_birth_weight_kg'])->toBe('1.38');
});

it('aggregates sow performance across litters', function () {
    $sow = register();
    $first = farrow($sow, ['farrowed_on' => now()->subDays(300)->startOfDay(), 'total_born' => 14, 'born_alive' => 12, 'stillborn' => 2, 'mummified' => 0]);
    app(RecordLitterLoss::class)($first, 2, now()->subDays(290)->startOfDay());
    app(WeanLitter::class)($first, now()->subDays(272)->startOfDay(), 10, '70');
    $second = farrow($sow, ['farrowed_on' => now()->subDays(150)->startOfDay(), 'total_born' => 10, 'born_alive' => 10, 'stillborn' => 0, 'mummified' => 0]);
    app(WeanLitter::class)($second, now()->subDays(122)->startOfDay(), 9, '60');
    $third = farrow($sow, ['farrowed_on' => now()->startOfDay()]);

    $perf = app(GetSowPerformance::class)($sow);

    expect($perf)->toMatchArray([
        'status' => 'lactating', 'parity' => 3, 'total_born' => 36,
        'avg_total_born' => '12.00', 'avg_born_alive' => '10.67',
        'total_weaned' => 19, 'avg_weaned' => '9.50',
        'pre_weaning_mortality_percent' => '6.25', // 2 lost of 32 born alive
        'weaning_percent' => '86.36', // 19 of 22 born alive in weaned litters
        'avg_farrowing_interval_days' => '150.00',
    ]);
    expect($third->litter_number)->toEndWith('-L03');
});

it('lists the expected breeding events in the calendar', function () {
    $served = register();
    $pending = serve($served, 20);                 // check due in 8 days, heat return in 1 day, farrowing in 94 days
    $lactating = register();
    $litter = farrow($lactating, ['farrowed_on' => now()->subDays(20)->startOfDay()]); // weaning due in 8 days
    $weaned = register();
    $done = farrow($weaned, ['farrowed_on' => now()->subDays(40)->startOfDay()]);
    app(WeanLitter::class)($done, now()->subDays(2)->startOfDay(), 10, '70'); // next service due in 3 days

    $types = fn (int $days) => app(GetBreedingCalendar::class)(now()->startOfDay(), now()->addDays($days)->endOfDay())
        ->mapWithKeys(fn ($e) => [$e['type'].'|'.$e['sow'] => $e['date']->toDateString()]);

    $soon = $types(10);
    expect($soon["Pregnancy check due|{$served->animal_number}"])->toBe($pending->expected_pregnancy_check_on->toDateString())
        ->and($soon)->toHaveKey("Watch for return to heat|{$served->animal_number}")
        ->and($soon["Weaning due|{$lactating->animal_number}"])->toBe($litter->expected_weaning_on->toDateString())
        ->and($soon)->toHaveKey("Next service due|{$weaned->animal_number}")
        ->and($soon)->not->toHaveKey("Farrowing expected|{$served->animal_number}")
        ->and($types(120))->toHaveKey("Farrowing expected|{$served->animal_number}");

    // Serving the weaned sow removes her "next service due" reminder.
    serve($weaned, 0);
    expect($types(10))->not->toHaveKey("Next service due|{$weaned->animal_number}");
});

it('keeps breeding history permanent', function () {
    $sow = register();
    $service = serve($sow, 114);
    $litter = farrow($sow);

    expect(fn () => $service->fresh()->update(['serviced_on' => now()]))->toThrow(LogicException::class)
        ->and(fn () => $service->fresh()->delete())->toThrow(LogicException::class)
        ->and(fn () => $litter->farrowing->update(['born_alive' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $litter->update(['litter_number' => 'X']))->toThrow(LogicException::class)
        ->and(fn () => $litter->delete())->toThrow(LogicException::class)
        ->and(AuditLog::where('auditable_type', $litter->getMorphClass())->where('event', 'created')->exists())->toBeTrue();
});
