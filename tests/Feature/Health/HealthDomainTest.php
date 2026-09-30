<?php

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Animal\Actions\RecordAnimalMovement;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Breed;
use App\Domain\Health\Actions\AssertAnimalCanEnterFoodChain;
use App\Domain\Health\Actions\ClearWithdrawal;
use App\Domain\Health\Actions\GetAnimalRestrictions;
use App\Domain\Health\Actions\GetHealthAlerts;
use App\Domain\Health\Actions\GetMortalityAnalysis;
use App\Domain\Health\Actions\GetVaccinationsDue;
use App\Domain\Health\Actions\RecordCulling;
use App\Domain\Health\Actions\RecordLabResult;
use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Health\Actions\RecordVaccination;
use App\Domain\Health\Actions\RecordVeterinaryVisit;
use App\Domain\Health\Actions\ReleaseQuarantine;
use App\Domain\Health\Actions\ReportHealthEvent;
use App\Domain\Health\Actions\ResolveHealthEvent;
use App\Domain\Health\Actions\StartQuarantine;
use App\Domain\Health\Models\Disease;
use App\Domain\Health\Models\MortalityRecord;
use App\Domain\Health\Models\Treatment;
use App\Domain\Health\Models\VaccinationSchedule;
use App\Domain\Health\Models\WithdrawalPeriod;
use App\Domain\Litter\Actions\RecordLitterLoss;
use App\Domain\Litter\Actions\RegisterLitterPiglets;
use App\Domain\Litter\Actions\WeanLitter;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\CullHealthStatus;
use App\Enums\DisposalType;
use App\Enums\HealthEventKind;
use App\Enums\HealthSeverity;
use App\Enums\LookupCategory;
use App\Enums\QuarantineType;
use App\Models\AuditLog;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

it('starts a withdrawal period automatically from the medicine\'s withdrawal days', function () {
    $animal = register();
    $treatment = treat($animal, medicine(7), '2026-06-01');

    $period = WithdrawalPeriod::firstOrFail();
    expect($treatment->withdrawal_days)->toBe(7)
        ->and($period->starts_on->toDateString())->toBe('2026-06-01')
        ->and($period->ends_on->toDateString())->toBe('2026-06-08')
        ->and($period->treatment_id)->toBe($treatment->id);

    treat(register(), medicine(0, 'other', 'NOWD'));
    expect(WithdrawalPeriod::count())->toBe(1);
});

it('lets a vet lengthen but not shorten a withdrawal period', function () {
    $antibiotic = medicine(5);

    expect(treat(register(), $antibiotic, null, ['withdrawal_days' => 10])->withdrawal_days)->toBe(10)
        ->and(fn () => treat(register(), $antibiotic, null, ['withdrawal_days' => 2]))->toThrow(DomainException::class, 'cannot be shorter');
});

it('validates treatments', function () {
    $animal = register();
    $med = medicine(0);
    $other = medicine(0, 'other', 'MED2');
    $expired = batchOf($med, 'OLD', now()->subDay()->toDateString());
    $inactive = batchOf($med, 'OFF');
    $inactive->update(['is_active' => false]);
    $foreign = batchOf($other, 'F-1');

    expect(fn () => treat($animal, $med, now()->addDay()->toDateString()))->toThrow(DomainException::class, 'between')
        ->and(fn () => treat($animal, $med, null, ['batch_id' => $expired->id]))->toThrow(DomainException::class, 'expired')
        ->and(fn () => treat($animal, $med, null, ['batch_id' => $inactive->id]))->toThrow(DomainException::class, 'not in use')
        ->and(fn () => treat($animal, $med, null, ['batch_id' => $foreign->id]))->toThrow(DomainException::class, 'not a batch of')
        ->and(fn () => treat($animal, $med, null, ['health_event_id' => 99999]))->toThrow(DomainException::class, 'does not belong');

    $med->update(['is_active' => false]);
    expect(fn () => treat($animal, $med))->toThrow(DomainException::class, 'inactive');

    app(RecordMortality::class)($animal, now(), cause());
    expect(fn () => treat($animal, medicine(0, 'other', 'MED3')))->toThrow(DomainException::class, 'can no longer');
});

it('accepts a batch that is still valid on the treatment date', function () {
    $med = medicine(0);
    $batch = batchOf($med, 'B-OK', now()->addDays(3)->toDateString());

    expect(treat(register(), $med, null, ['batch_id' => $batch->id])->batch->is($batch))->toBeTrue();
});

it('replays a retried treatment only for the same animal', function () {
    $animal = register();
    $med = medicine(3);
    $first = treat($animal, $med, null, ['idempotency_key' => 'dev3-0001']);

    expect(treat($animal, $med, null, ['idempotency_key' => 'dev3-0001'])->is($first))->toBeTrue()
        ->and(WithdrawalPeriod::count())->toBe(1)
        ->and(fn () => treat(register(), $med, null, ['idempotency_key' => 'dev3-0001']))->toThrow(DomainException::class, 'different animal');
});

it('blocks sale and slaughter while an animal is under withdrawal, and allows them afterwards', function () {
    $animal = register();
    $other = register();
    treat($animal, medicine(7));            // today + 7 days
    treat($other, medicine(3, 'other', 'MED3'), now()->subDays(3)->toDateString()); // ended today: safe from today
    $change = app(ChangeAnimalStatus::class);

    expect(fn () => $change($animal, AnimalStatus::Sold, 'Sold'))->toThrow(DomainException::class, 'under withdrawal for Medicine MED1')
        ->and(fn () => $change($animal, AnimalStatus::Slaughtered, 'Slaughter'))->toThrow(DomainException::class, 'under withdrawal')
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Active);

    expect($change($other, AnimalStatus::Sold, 'Sold')->to_status)->toBe(AnimalStatus::Sold);

    $much = register();
    treat($much, medicine(7, 'other', 'MED9'));
    expect($change($much, AnimalStatus::Sold, 'Sold after the period', now()->addDays(8))->to_status)->toBe(AnimalStatus::Sold);
});

it('does not stop an animal that dies, is culled or leaves the farm for other reasons', function () {
    $medicine = medicine(30);
    $dying = register();
    $leaving = register();
    treat($dying, $medicine);
    treat($leaving, $medicine);

    expect(app(RecordMortality::class)($dying, now(), cause())->exists)->toBeTrue()
        ->and(app(ChangeAnimalStatus::class)($leaving, AnimalStatus::TransferredOut, 'Sent to another farm')->to_status)->toBe(AnimalStatus::TransferredOut);
});

it('clears a withdrawal early only with a reason, leaving an audit trail', function () {
    $animal = register();
    treat($animal, medicine(30));
    $period = WithdrawalPeriod::firstOrFail();

    expect(fn () => app(ClearWithdrawal::class)($period, ' '))->toThrow(DomainException::class, 'reason');

    $this->actingAs($vet = userWithRole('Veterinarian'));
    app(ClearWithdrawal::class)($period, 'Residue test negative');

    expect($period->fresh()->cleared_by)->toBe($vet->id)
        ->and($period->fresh()->clear_reason)->toBe('Residue test negative')
        ->and(fn () => app(ClearWithdrawal::class)($period->fresh(), 'again'))->toThrow(DomainException::class, 'already cleared')
        ->and(AuditLog::where('auditable_type', $period->getMorphClass())->where('event', 'updated')->exists())->toBeTrue()
        ->and(app(ChangeAnimalStatus::class)($animal, AnimalStatus::Sold, 'Sold')->to_status)->toBe(AnimalStatus::Sold)
        ->and(fn () => $period->update(['ends_on' => now()]))->toThrow(LogicException::class);
});

it('reports what restricts an animal', function () {
    $animal = register();
    expect(app(GetAnimalRestrictions::class)($animal)['withdrawals'])->toBeEmpty();

    treat($animal, medicine(10));
    app(StartQuarantine::class)($animal, QuarantineType::Isolation, now(), 'Cough');
    $r = app(GetAnimalRestrictions::class)($animal);

    expect($r['withdrawals'])->toHaveCount(1)->and($r['quarantine']->type)->toBe(QuarantineType::Isolation)
        ->and(fn () => app(AssertAnimalCanEnterFoodChain::class)($animal))->toThrow(DomainException::class);
});

it('keeps quarantined animals from being sold until released', function () {
    $animal = register();
    $record = app(StartQuarantine::class)($animal, QuarantineType::Quarantine, now(), 'New arrival');

    expect(fn () => app(ChangeAnimalStatus::class)($animal, AnimalStatus::Sold, 'Sold'))->toThrow(DomainException::class, 'in quarantine');

    app(ReleaseQuarantine::class)($record, now(), 'Clear after 14 days');

    expect($record->fresh()->isOpen())->toBeFalse()
        ->and(fn () => app(ReleaseQuarantine::class)($record->fresh(), now()))->toThrow(DomainException::class, 'already been released')
        ->and(app(ChangeAnimalStatus::class)($animal, AnimalStatus::Sold, 'Sold')->to_status)->toBe(AnimalStatus::Sold);
});

it('moves an animal into quarantine and validates the request', function () {
    $animal = register(['pen_id' => newPen('HOME')->id]);
    $sickBay = newPen('SICK');
    $start = app(StartQuarantine::class);

    $start($animal, QuarantineType::Quarantine, now(), 'Suspected ASF', $sickBay->id);

    expect($animal->fresh()->currentPen->is($sickBay))->toBeTrue()
        ->and(fn () => $start($animal, QuarantineType::Isolation, now(), 'Again'))->toThrow(DomainException::class, 'already in quarantine')
        ->and(fn () => $start(register(), QuarantineType::Isolation, now(), ' '))->toThrow(DomainException::class, 'reason')
        ->and(fn () => $start(register(), QuarantineType::Isolation, now()->addDay(), 'Future'))->toThrow(DomainException::class, 'between');
});

it('records vaccinations against a schedule with the right vaccine, category and batch', function () {
    $vac = vaccine('PARVO', 0);
    $schedule = VaccinationSchedule::create(['code' => 'PARVO1', 'name' => 'Parvovirus', 'medicine_id' => $vac->id, 'category_id' => categoryId('gilt'), 'first_dose_age_days' => 150, 'repeat_interval_days' => 180]);
    $gilt = register(['category_id' => categoryId('gilt')]);
    $sow = register();
    $batch = batchOf($vac, 'PV-9');
    $give = app(RecordVaccination::class);

    $done = $give($gilt, now(), ['schedule_id' => $schedule->id, 'batch_id' => $batch->id, 'dose' => '2']);

    expect($done->medicine->is($vac))->toBeTrue()
        ->and($done->schedule->is($schedule))->toBeTrue()
        ->and(fn () => $give($gilt, now(), ['schedule_id' => $schedule->id]))->toThrow(DomainException::class, 'already recorded')
        ->and(fn () => $give($sow, now(), ['schedule_id' => $schedule->id]))->toThrow(DomainException::class, 'category')
        ->and(fn () => $give($gilt, now()->subDay(), ['schedule_id' => $schedule->id, 'medicine_id' => vaccine('OTHER')->id]))->toThrow(DomainException::class, 'not the one in the vaccination schedule')
        ->and(fn () => $give($gilt, now()->subDay(), ['medicine_id' => medicine(0, 'other', 'NOTVAC')->id]))->toThrow(DomainException::class, 'not a vaccine')
        ->and(fn () => $give($gilt, now()->subDay(), []))->toThrow(DomainException::class, 'Choose the vaccine');

    $schedule->update(['is_active' => false]);
    expect(fn () => $give($gilt, now()->subDays(2), ['schedule_id' => $schedule->id]))->toThrow(DomainException::class, 'inactive');
});

it('gives an ad-hoc vaccination and starts a withdrawal if the vaccine has one', function () {
    $animal = register();

    $v = app(RecordVaccination::class)($animal, now(), ['medicine_id' => vaccine('ERY', 21)->id]);

    expect($v->withdrawal_days)->toBe(21)
        ->and(WithdrawalPeriod::firstOrFail()->vaccination_id)->toBe($v->id)
        ->and(fn () => app(ChangeAnimalStatus::class)($animal, AnimalStatus::Sold, 'Sold'))->toThrow(DomainException::class, 'withdrawal');
});

it('lists vaccinations due and overdue from the schedules', function () {
    $vac = vaccine('CSF');
    $schedule = VaccinationSchedule::create(['code' => 'CSF1', 'name' => 'Swine fever', 'medicine_id' => $vac->id, 'category_id' => categoryId('piglet'), 'first_dose_age_days' => 42, 'repeat_interval_days' => 90]);
    $overdue = register(['category_id' => categoryId('piglet'), 'birth_date' => now()->subDays(50)->toDateString()]);
    $soon = register(['category_id' => categoryId('piglet'), 'birth_date' => now()->subDays(35)->toDateString()]);
    $notYet = register(['category_id' => categoryId('piglet'), 'birth_date' => now()->subDays(10)->toDateString()]);
    register(['category_id' => categoryId('piglet')]);                                   // no birth date: cannot be scheduled
    register(['category_id' => categoryId('grower'), 'birth_date' => now()->subDays(60)->toDateString()]); // other category
    $due = fn () => app(GetVaccinationsDue::class)()->mapWithKeys(fn ($d) => [$d['animal']->animal_number => $d['overdue']]);

    expect($due()->all())->toBe([$overdue->animal_number => true, $soon->animal_number => false]);

    // Given today, the next dose is a booster 90 days away: out of the reminder window.
    app(RecordVaccination::class)($overdue, now(), ['schedule_id' => $schedule->id]);
    expect($due()->keys()->all())->toBe([$soon->animal_number]);

    // A booster falling due in 10 days appears; the same animal is overdue once the interval has passed.
    $booster = register(['category_id' => categoryId('piglet'), 'birth_date' => now()->subDays(200)->toDateString()]);
    app(RecordVaccination::class)($booster, now()->subDays(80), ['schedule_id' => $schedule->id]);
    expect($due()->get($booster->animal_number))->toBeFalse()
        ->and(app(GetVaccinationsDue::class)(now()->addDays(30))->firstWhere('animal.id', $booster->id)['overdue'])->toBeTrue()
        ->and(app(GetVaccinationsDue::class)(null, 100)->pluck('animal.animal_number')->all())->toContain($overdue->animal_number, $notYet->animal_number);

    app(RecordMortality::class)($booster, now(), cause());
    expect($due()->has($booster->animal_number))->toBeFalse();
});

it('treats a single-dose schedule as finished once given', function () {
    $schedule = VaccinationSchedule::create(['code' => 'ONCE', 'name' => 'Once only', 'medicine_id' => vaccine('ONE')->id, 'first_dose_age_days' => 10]);
    $animal = register(['birth_date' => now()->subDays(20)->toDateString()]);

    expect(app(GetVaccinationsDue::class)()->pluck('animal.id')->all())->toBe([$animal->id]);

    app(RecordVaccination::class)($animal, now(), ['schedule_id' => $schedule->id]);
    expect(app(GetVaccinationsDue::class)(null, 3650))->toBeEmpty();
});

it('opens and resolves health cases with validation', function () {
    $animal = register();
    $disease = Disease::create(['code' => 'SCOURS', 'name' => 'Scours']);
    $event = app(ReportHealthEvent::class)($animal, HealthEventKind::Illness, HealthSeverity::Moderate, now()->subDay(), 'Diarrhoea', $disease->id);

    expect($event->isOpen())->toBeTrue()
        ->and(fn () => app(ReportHealthEvent::class)($animal, HealthEventKind::Injury, HealthSeverity::Mild, now()->addDay()))->toThrow(DomainException::class, 'between')
        ->and(fn () => app(ResolveHealthEvent::class)($event, now()->subDays(5)))->toThrow(DomainException::class, 'between');

    treat($animal, medicine(0), null, ['health_event_id' => $event->id]);
    app(ResolveHealthEvent::class)($event, now(), 'Recovered');

    expect($event->fresh()->isOpen())->toBeFalse()
        ->and($event->treatments()->count())->toBe(1)
        ->and(fn () => app(ResolveHealthEvent::class)($event->fresh(), now()))->toThrow(DomainException::class, 'already resolved')
        ->and(fn () => $event->fresh()->update(['symptoms' => 'changed']))->toThrow(LogicException::class);
});

it('records veterinary visits and lab results with validation', function () {
    $visit = app(RecordVeterinaryVisit::class)(now(), 'Herd check', ['veterinarian_name' => 'Dr Ada', 'follow_up_on' => now()->addWeek(), 'cost_minor' => 1500000]);

    expect($visit->cost_minor)->toBe(1500000)
        ->and(fn () => app(RecordVeterinaryVisit::class)(now(), 'Herd check'))->toThrow(DomainException::class, 'veterinarian')
        ->and(fn () => app(RecordVeterinaryVisit::class)(now(), 'x', ['veterinarian_name' => 'A', 'follow_up_on' => now()->subDay()]))->toThrow(DomainException::class, 'follow-up');

    $lab = app(RecordLabResult::class)('Blood', 'Brucella', now()->subDays(2), ['animal_id' => register()->id, 'resulted_on' => now(), 'result' => 'Negative', 'veterinary_visit_id' => $visit->id]);

    expect($lab->is_abnormal)->toBeFalse()
        ->and(fn () => app(RecordLabResult::class)('Blood', 'Brucella', now(), ['resulted_on' => now()->subDay()]))->toThrow(DomainException::class, 'Dates must run')
        ->and(fn () => app(RecordLabResult::class)(' ', 'Test', now()))->toThrow(DomainException::class, 'sample type');
});

it('records a death with everything needed to analyse it later', function () {
    $sow = register();
    $litter = farrow($sow, ['farrowed_on' => now()->subDays(10)->startOfDay()]);
    $pen = newPen('FARROW1');
    $piglet = app(RegisterLitterPiglets::class)($litter, [['sex' => 'male', 'birth_weight_kg' => '1.4']], $pen->id)->first();
    $record = app(RecordMortality::class)($piglet, now(), cause('crushed'), null, 'Found in the morning');

    expect($piglet->fresh()->status)->toBe(AnimalStatus::Dead)
        ->and($record->age_days)->toBe(10)
        ->and($record->pen->is($pen))->toBeTrue()
        ->and($record->category->code)->toBe('piglet')
        ->and($record->litter->is($litter))->toBeTrue()
        ->and($record->sow->is($sow))->toBeTrue()
        ->and($record->weight_kg)->toBe('1.40')
        ->and($record->cause->code)->toBe('crushed')
        ->and($piglet->fresh()->movements->first()->toLabel())->toBe('Left the farm')
        ->and($litter->losses()->count())->toBe(1)
        ->and($litter->losses()->first()->animal_id)->toBe($piglet->id)
        ->and(fn () => $record->update(['notes' => 'x']))->toThrow(LogicException::class);
});

it('validates mortality records', function () {
    $animal = register();
    $die = app(RecordMortality::class);

    expect(fn () => $die($animal, now()->addDay(), cause()))->toThrow(DomainException::class, 'date of death')
        ->and(fn () => $die($animal, now(), 999999))->toThrow(DomainException::class, 'valid cause')
        ->and(fn () => $die($animal, now(), cause(), 999999))->toThrow(DomainException::class, 'valid disease');

    $die($animal, now(), cause());
    expect(fn () => $die($animal, now(), cause()))->toThrow(DomainException::class, 'already Dead');
});

it('records a tracked piglet death exactly once whichever way it is entered', function () {
    $litter = farrow(register());
    [$a, $b] = app(RegisterLitterPiglets::class)($litter, [['sex' => 'male'], ['sex' => 'female']])->all();

    app(RecordMortality::class)($a, now(), cause('scours'));
    app(RecordLitterLoss::class)($litter, 1, now(), 'Crushed', $b->id, null, cause('crushed'));

    expect($litter->losses()->sum('count'))->toBe(2)
        ->and(MortalityRecord::count())->toBe(2)
        ->and(MortalityRecord::where('animal_id', $b->id)->first()->cause->code)->toBe('crushed');

    // A loss entered by name only falls back to the "unknown" cause.
    [$c] = app(RegisterLitterPiglets::class)(farrow(register()), [['sex' => 'male']])->all();
    app(RecordLitterLoss::class)($c->parentage->litter, 1, now(), 'Weak', $c->id);
    expect(MortalityRecord::where('animal_id', $c->id)->first()->cause->code)->toBe('unknown');
});

it('analyses mortality by every dimension, including untracked litter losses', function () {
    $sow = register(['breed_id' => Breed::firstWhere('code', 'LW')->id]);
    $litter = farrow($sow, ['farrowed_on' => now()->subDays(20)->startOfDay()]);
    $pen = newPen('GRP1');
    $piglet = app(RegisterLitterPiglets::class)($litter, [['sex' => 'male'], ['sex' => 'female']], $pen->id)->first();
    app(RecordMortality::class)($piglet, now(), cause('scours'));                                  // age 20: band 8-28
    app(RecordLitterLoss::class)($litter, 2, now()->subDays(15)->startOfDay(), 'Crushed');        // untracked: age 5, band 0-7
    $grower = register(['category_id' => categoryId('grower'), 'birth_date' => now()->subDays(100)->toDateString(), 'pen_id' => $pen->id]);
    app(RecordMortality::class)($grower, now(), cause('respiratory'));                            // age 100: band 71-150

    $analyse = fn (string $dim) => app(GetMortalityAnalysis::class)(now()->subMonth(), now(), $dim);
    $by = fn (string $dim) => $analyse($dim)['rows']->mapWithKeys(fn ($r) => [$r['label'] => $r['count']])->all();

    expect($analyse('stage')['total'])->toBe(4)
        ->and($by('stage'))->toBe(['Piglet' => 3, 'Grower' => 1])
        ->and($by('age_band'))->toBe(['0-7 days' => 2, '8-28 days' => 1, '71-150 days' => 1])
        ->and($by('litter'))->toBe([$litter->litter_number => 3, 'Unknown' => 1])
        ->and($by('sow'))->toBe([$sow->animal_number => 3, 'Unknown' => 1])
        ->and($by('breed')['Large White'])->toBe(3)
        ->and($by('pen'))->toBe(['GRP1' => 2, 'Unknown' => 2])
        ->and($by('cause'))->toMatchArray(['Crushed' => 2, 'Scours / diarrhoea' => 1, 'Respiratory disease' => 1])
        ->and($by('month'))->toHaveKey(now()->format('Y-m'))
        ->and($analyse('stage')['rows']->first()['percent'])->toBe('75.00')
        ->and(fn () => $analyse('colour'))->toThrow(InvalidArgumentException::class);
});

it('honours the configured mortality age bands and the reporting period', function () {
    app(ResolveSettings::class)->set('health.mortality_age_band_limits', '10, 30');
    $old = register(['birth_date' => now()->subDays(90)->toDateString()]);
    $young = register(['birth_date' => now()->subDays(5)->toDateString()]);
    app(RecordMortality::class)($old, now()->subDays(50), cause());
    app(RecordMortality::class)($young, now(), cause());

    $rows = app(GetMortalityAnalysis::class)(now()->subDays(10), now(), 'age_band')['rows'];

    expect($rows->pluck('label')->all())->toBe(['0-10 days']);
    expect(app(GetMortalityAnalysis::class)(now()->subYear(), now(), 'age_band')['rows']->pluck('label')->sort()->values()->all())->toBe(['0-10 days', '31+ days']);
});

it('culls an animal keeping weight, health status, performance and value', function () {
    $sow = register();
    farrow($sow, ['farrowed_on' => now()->subDays(60)->startOfDay()]);
    app(WeanLitter::class)($sow->litters->first(), now()->subDays(30)->startOfDay(), 9, '60');

    $record = app(RecordCulling::class)($sow, now(), lookup(LookupCategory::CullReason, 'poor_performance'), '210.50', CullHealthStatus::Healthy, DisposalType::Sold, 45000000, 'Sold to trader');

    expect($record->weight_kg)->toBe('210.50')
        ->and($record->disposal_value_minor)->toBe(45000000)
        ->and($record->performance['parity'])->toBe(1)
        ->and($record->performance['total_weaned'])->toBe(9)
        ->and($record->performance['category'])->toBe('Sow')
        ->and($sow->fresh()->status)->toBe(AnimalStatus::Culled)
        ->and($sow->fresh()->latestWeight()->weight_kg)->toBe('210.50')
        ->and(fn () => $record->update(['notes' => 'x']))->toThrow(LogicException::class);
});

it('blocks culling for sale while under withdrawal but allows destruction', function () {
    $medicine = medicine(20);
    $forSale = register();
    $destroy = register();
    treat($forSale, $medicine);
    treat($destroy, $medicine);
    $reason = lookup(LookupCategory::CullReason, 'disease');
    $cull = app(RecordCulling::class);

    expect(fn () => $cull($forSale, now(), $reason, '90', CullHealthStatus::Sick, DisposalType::Sold, 100))->toThrow(DomainException::class, 'under withdrawal')
        ->and($forSale->fresh()->status)->toBe(AnimalStatus::Active)
        ->and($forSale->weights()->count())->toBe(0)
        ->and($cull($destroy, now(), $reason, '90', CullHealthStatus::Sick, DisposalType::Destroyed, 0)->exists)->toBeTrue();
});

it('validates culling input', function () {
    $animal = register();
    $reason = lookup(LookupCategory::CullReason, 'age');
    $cull = app(RecordCulling::class);

    expect(fn () => $cull($animal, now(), 999999, '100', CullHealthStatus::Healthy, DisposalType::Other, 0))->toThrow(DomainException::class, 'valid culling reason')
        ->and(fn () => $cull($animal, now(), $reason, '100', CullHealthStatus::Healthy, DisposalType::Other, -1))->toThrow(DomainException::class, 'negative')
        ->and(fn () => $cull($animal, now(), $reason, '-5', CullHealthStatus::Healthy, DisposalType::Other, 0))->toThrow(DomainException::class, 'positive')
        ->and(fn () => $cull($animal, now()->addDay(), $reason, '100', CullHealthStatus::Healthy, DisposalType::Other, 0))->toThrow(DomainException::class, 'culling date')
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Active);
});

it('places culling and mortality after the animal\'s latest movement on the same day', function () {
    $animal = register(['pen_id' => newPen('A')->id]);
    app(RecordAnimalMovement::class)($animal, newPen('B')->id);

    $record = app(RecordCulling::class)($animal, now(), lookup(LookupCategory::CullReason, 'other'), '80', CullHealthStatus::PoorCondition, DisposalType::Destroyed, 0);

    expect($record->exists)->toBeTrue()->and($animal->fresh()->currentPen)->toBeNull();
});

it('raises health alerts for what needs attention', function () {
    $vac = vaccine('AL1');
    VaccinationSchedule::create(['code' => 'AL', 'name' => 'Alert vax', 'medicine_id' => $vac->id, 'first_dose_age_days' => 10]);
    $overdue = register(['birth_date' => now()->subDays(40)->toDateString()]);
    $med = medicine(0, 'other', 'ALM');
    batchOf($med, 'EXP', now()->subDays(5)->toDateString());
    batchOf($med, 'SOON', now()->addDays(20)->toDateString());
    batchOf($med, 'FINE', now()->addYear()->toDateString());
    $sick = register();
    app(ReportHealthEvent::class)($sick, HealthEventKind::Illness, HealthSeverity::Mild, now()->subDays(10));
    $quarantined = register();
    app(StartQuarantine::class)($quarantined, QuarantineType::Quarantine, now()->subDays(30), 'Arrival');
    app(RecordVeterinaryVisit::class)(now()->subDays(10), 'Herd check', ['veterinarian_name' => 'Dr Ada', 'follow_up_on' => now()->subDay()]);

    $messages = app(GetHealthAlerts::class)()->pluck('message', 'type');

    expect($messages['vaccination'])->toContain($overdue->animal_number, 'overdue')
        ->and(app(GetHealthAlerts::class)()->where('type', 'batch_expiry')->pluck('message')->implode('|'))->toContain('EXP', 'SOON')->not->toContain('FINE')
        ->and($messages['open_case'])->toContain($sick->animal_number)
        ->and($messages['long_quarantine'])->toContain($quarantined->animal_number)
        ->and($messages['vet_follow_up'])->toContain('Herd check')
        ->and(app(GetHealthAlerts::class)()->first()['severity'])->toBe('danger');
});

it('keeps referenced health master data from being deleted', function () {
    $owner = owner();
    $med = medicine(0);
    $unused = medicine(0, 'other', 'UNUSED');
    treat(register(), $med);

    expect($owner->can('delete', $med))->toBeFalse()
        ->and($owner->can('delete', $unused))->toBeTrue()
        ->and($owner->can('delete', WithdrawalPeriod::first()))->toBeFalse()
        ->and($owner->can('delete', Treatment::first()))->toBeFalse();
});
