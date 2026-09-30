<?php

use App\Domain\Biosecurity\Models\BiosecurityCheck;
use App\Domain\Biosecurity\Models\BiosecurityVisit;
use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Health\Actions\ReportHealthEvent;
use App\Domain\Health\Actions\StartQuarantine;
use App\Domain\Health\Models\CullingRecord;
use App\Domain\Health\Models\HealthEvent;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\MortalityRecord;
use App\Domain\Health\Models\QuarantineRecord;
use App\Domain\Health\Models\Treatment;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Health\Models\VaccinationSchedule;
use App\Domain\Health\Models\WithdrawalPeriod;
use App\Enums\AnimalStatus;
use App\Enums\HealthEventKind;
use App\Enums\HealthSeverity;
use App\Enums\LookupCategory;
use App\Enums\QuarantineType;
use App\Filament\Pages\HealthAlerts;
use App\Filament\Pages\MortalityAnalysis;
use App\Filament\Pages\VaccinationsDue;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Animals\Pages\ViewAnimal;
use App\Filament\Resources\BiosecurityChecklistItems\BiosecurityChecklistItemResource;
use App\Filament\Resources\BiosecurityChecklistItems\Pages\CreateBiosecurityChecklistItem;
use App\Filament\Resources\BiosecurityChecks\BiosecurityCheckResource;
use App\Filament\Resources\BiosecurityChecks\Pages\CreateBiosecurityCheck;
use App\Filament\Resources\BiosecurityVisits\BiosecurityVisitResource;
use App\Filament\Resources\BiosecurityVisits\Pages\CreateBiosecurityVisit;
use App\Filament\Resources\BiosecurityVisits\Pages\ListBiosecurityVisits;
use App\Filament\Resources\Culling\CullingResource;
use App\Filament\Resources\Culling\Pages\CreateCulling;
use App\Filament\Resources\Diseases\DiseaseResource;
use App\Filament\Resources\HealthEvents\HealthEventResource;
use App\Filament\Resources\HealthEvents\Pages\CreateHealthEvent;
use App\Filament\Resources\HealthEvents\Pages\ListHealthEvents;
use App\Filament\Resources\LabResults\LabResultResource;
use App\Filament\Resources\Medicines\MedicineResource;
use App\Filament\Resources\Medicines\Pages\CreateMedicine;
use App\Filament\Resources\Medicines\Pages\EditMedicine;
use App\Filament\Resources\Medicines\RelationManagers\BatchesRelationManager;
use App\Filament\Resources\Mortality\MortalityResource;
use App\Filament\Resources\Mortality\Pages\CreateMortality;
use App\Filament\Resources\Quarantine\Pages\CreateQuarantine;
use App\Filament\Resources\Quarantine\Pages\ListQuarantines;
use App\Filament\Resources\Quarantine\QuarantineResource;
use App\Filament\Resources\Treatments\Pages\CreateTreatment;
use App\Filament\Resources\Treatments\TreatmentResource;
use App\Filament\Resources\Vaccinations\Pages\CreateVaccination;
use App\Filament\Resources\Vaccinations\VaccinationResource;
use App\Filament\Resources\VaccinationSchedules\Pages\CreateVaccinationSchedule;
use App\Filament\Resources\VaccinationSchedules\VaccinationScheduleResource;
use App\Filament\Resources\VeterinaryVisits\VeterinaryVisitResource;
use App\Filament\Resources\WithdrawalPeriods\Pages\ListWithdrawalPeriods;
use App\Filament\Resources\WithdrawalPeriods\WithdrawalPeriodResource;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('renders every health and biosecurity page for the owner', function () {
    $this->actingAs(owner());
    $animal = register();
    treat($animal, medicine(5));

    foreach ([
        HealthEventResource::class, TreatmentResource::class,
        VaccinationResource::class, WithdrawalPeriodResource::class,
        VeterinaryVisitResource::class, LabResultResource::class,
        QuarantineResource::class, MortalityResource::class,
        CullingResource::class, DiseaseResource::class, MedicineResource::class,
        VaccinationScheduleResource::class,
        BiosecurityVisitResource::class, BiosecurityCheckResource::class,
        BiosecurityChecklistItemResource::class,
    ] as $resource) {
        $this->get($resource::getUrl('index'))->assertOk();
        array_key_exists('create', $resource::getPages()) && $this->get($resource::getUrl('create'))->assertOk();
    }

    $this->get(HealthAlerts::getUrl())->assertOk();
    $this->get(VaccinationsDue::getUrl())->assertOk();
    $this->get(MortalityAnalysis::getUrl())->assertOk();
    $this->get(AnimalResource::getUrl('view', ['record' => $animal]))->assertOk()->assertSee('Withdrawal');
});

it('runs the health buttons on the animal profile', function () {
    $this->actingAs(owner());
    $animal = register();
    $med = medicine(7);
    $batch = batchOf($med, 'TB-1');
    $page = Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()]);

    $page->callAction('treat', ['medicine_id' => $med->id, 'batch_id' => $batch->id, 'administered_on' => now()->toDateString(), 'dose' => 5, 'dose_unit' => 'ml'])
        ->assertNotified('Treatment recorded');
    expect(Treatment::first()->batch->is($batch))->toBeTrue()
        ->and(WithdrawalPeriod::count())->toBe(1);
    $page->assertSee('Until');

    $page->callAction('report_case', ['kind' => 'illness', 'severity' => 'mild', 'observed_on' => now()->toDateString(), 'symptoms' => 'Cough'])
        ->assertNotified('Health case opened');
    expect(HealthEvent::first()->animal->is($animal))->toBeTrue();

    $page->callAction('quarantine', ['type' => 'isolation', 'started_on' => now()->toDateString(), 'reason' => 'Cough'])
        ->assertNotified('Animal quarantined');
    expect(QuarantineRecord::first()->isOpen())->toBeTrue();
});

it('shows a withdrawal block when trying to sell from the profile', function () {
    $this->actingAs(owner());
    $animal = register();
    treat($animal, medicine(14));

    Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()])
        ->callAction('status', ['status' => 'sold', 'reason' => 'Market day', 'changed_at' => now()->toDateTimeString()])
        ->assertNotified('Not saved');

    expect($animal->fresh()->status)->toBe(AnimalStatus::Active);
});

it('vaccinates from the profile using a schedule', function () {
    $this->actingAs(owner());
    $animal = register();
    $schedule = VaccinationSchedule::create(['code' => 'S1', 'name' => 'Sched', 'medicine_id' => vaccine('V1')->id, 'first_dose_age_days' => 1]);

    Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()])
        ->callAction('vaccinate', ['schedule_id' => $schedule->id, 'administered_on' => now()->toDateString()])
        ->assertNotified('Vaccination recorded');

    expect(Vaccination::first()->schedule->is($schedule))->toBeTrue();
});

it('records a death and a cull from the profile, with the terminal status actions kept separate', function () {
    $this->actingAs(owner());
    $dies = register();
    $culled = register();

    Livewire::test(ViewAnimal::class, ['record' => $dies->getRouteKey()])
        ->callAction('death', ['died_on' => now()->toDateString(), 'cause_id' => cause('injury'), 'notes' => 'Found dead'])
        ->assertNotified('Death recorded');
    expect(MortalityRecord::first()->animal->is($dies))->toBeTrue()->and($dies->fresh()->status)->toBe(AnimalStatus::Dead);

    Livewire::test(ViewAnimal::class, ['record' => $culled->getRouteKey()])
        ->callAction('cull', [
            'culled_on' => now()->toDateString(), 'reason_id' => lookup(LookupCategory::CullReason, 'age'), 'weight_kg' => 180,
            'health_status' => 'healthy', 'disposal' => 'sold', 'disposal_value_minor' => '350000.50',
        ])
        ->assertNotified('Animal culled');
    expect(CullingRecord::first()->disposal_value_minor)->toBe(35000050);
});

it('creates health records through their forms and reports rule violations', function () {
    $this->actingAs(owner());
    $animal = register();
    $med = medicine(0);

    Livewire::test(CreateTreatment::class)
        ->fillForm(['animal_id' => $animal->id, 'medicine_id' => $med->id, 'administered_on' => now()->toDateString(), 'route' => 'oral'])
        ->call('create')->assertHasNoFormErrors();
    expect(Treatment::count())->toBe(1);

    Livewire::test(CreateTreatment::class)
        ->fillForm(['animal_id' => $animal->id, 'medicine_id' => $med->id, 'administered_on' => now()->toDateString(), 'withdrawal_days' => 0])
        ->call('create')->assertHasNoFormErrors();

    $expired = batchOf($med, 'OLD', now()->subDay()->toDateString());
    Livewire::test(CreateTreatment::class)
        ->fillForm(['animal_id' => $animal->id, 'medicine_id' => $med->id, 'batch_id' => $expired->id, 'administered_on' => now()->toDateString()])
        ->call('create')->assertHasFormErrors(['batch_id']); // the form never offers expired batches; the domain action is the second layer
    expect(Treatment::count())->toBe(2);

    Livewire::test(CreateTreatment::class)
        ->fillForm(['animal_id' => register()->id, 'medicine_id' => $med->id, 'administered_on' => now()->toDateString(), 'withdrawal_days' => 0])
        ->call('create')->assertHasNoFormErrors();

    Livewire::test(CreateHealthEvent::class)
        ->fillForm(['animal_id' => $animal->id, 'kind' => 'injury', 'severity' => 'severe', 'observed_on' => now()->toDateString()])
        ->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateVaccination::class)
        ->fillForm(['animal_id' => $animal->id, 'medicine_id' => vaccine('V2')->id, 'administered_on' => now()->toDateString()])
        ->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateQuarantine::class)
        ->fillForm(['animal_id' => $animal->id, 'type' => 'quarantine', 'started_on' => now()->toDateString(), 'reason' => 'Arrival'])
        ->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateMortality::class)
        ->fillForm(['animal_id' => register()->id, 'died_on' => now()->toDateString(), 'cause_id' => cause()])
        ->call('create')->assertHasNoFormErrors();

    expect(HealthEvent::count())->toBe(1)->and(Vaccination::count())->toBe(1)->and(QuarantineRecord::count())->toBe(1)->and(MortalityRecord::count())->toBe(1);
});

it('only lets people with approval culls animals through the culling form', function () {
    $animal = register();
    $data = ['animal_id' => $animal->id, 'culled_on' => now()->toDateString(), 'reason_id' => lookup(LookupCategory::CullReason, 'age'), 'weight_kg' => 150, 'health_status' => 'healthy', 'disposal' => 'destroyed', 'disposal_value_minor' => '0'];

    $this->actingAs(farmWorker());
    expect(CullingResource::canCreate())->toBeFalse();
    $this->get(CullingResource::getUrl('create'))->assertForbidden();

    $this->actingAs(userWithRole('Farm Manager'));
    expect(CullingResource::canCreate())->toBeTrue();
    Livewire::test(CreateCulling::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
    expect(CullingRecord::count())->toBe(1);
});

it('resolves cases, releases quarantine and clears withdrawals from their lists', function () {
    $this->actingAs(owner());
    $animal = register();
    $event = app(ReportHealthEvent::class)($animal, HealthEventKind::Illness, HealthSeverity::Mild, now());
    $quarantine = app(StartQuarantine::class)($animal, QuarantineType::Isolation, now(), 'Cough');
    treat($animal, medicine(20));
    $period = WithdrawalPeriod::firstOrFail();

    Livewire::test(ListHealthEvents::class)
        ->callAction(TestAction::make('resolve')->table($event), ['resolved_on' => now()->toDateString(), 'notes' => 'Better'])
        ->assertNotified('Health case resolved');
    Livewire::test(ListQuarantines::class)
        ->callAction(TestAction::make('release')->table($quarantine), ['released_on' => now()->toDateString()])
        ->assertNotified('Animal released');
    Livewire::test(ListWithdrawalPeriods::class)
        ->callAction(TestAction::make('clear')->table($period), ['reason' => 'Negative residue test'])
        ->assertNotified('Withdrawal cleared');

    expect($event->fresh()->isOpen())->toBeFalse()
        ->and($quarantine->fresh()->isOpen())->toBeFalse()
        ->and($period->fresh()->cleared_at)->not->toBeNull();
});

it('hides early withdrawal clearing from people without approval', function () {
    $animal = register();
    treat($animal, medicine(20));
    $period = WithdrawalPeriod::firstOrFail();

    $this->actingAs(farmWorker());
    Livewire::test(ListWithdrawalPeriods::class)->assertActionHidden(TestAction::make('clear')->table($period));

    $this->actingAs(userWithRole('Veterinarian'));
    Livewire::test(ListWithdrawalPeriods::class)->assertActionVisible(TestAction::make('clear')->table($period));
});

it('creates medicines and schedules through the forms', function () {
    $this->actingAs(owner());

    Livewire::test(CreateMedicine::class)
        ->fillForm(['code' => 'amox', 'name' => 'Amoxicillin', 'type_id' => lookup(LookupCategory::MedicineType, 'antibiotic'), 'default_withdrawal_days' => 10])
        ->call('create')->assertHasNoFormErrors();
    $medicine = Medicine::firstWhere('code', 'AMOX');
    expect($medicine->default_withdrawal_days)->toBe(10)->and($medicine->is_active)->toBeTrue();

    Livewire::test(BatchesRelationManager::class, ['ownerRecord' => $medicine, 'pageClass' => EditMedicine::class])
        ->callAction(TestAction::make('create')->table(), ['batch_number' => 'LOT-77', 'expiry_date' => now()->addYear()->toDateString()])
        ->assertHasNoFormErrors();
    expect($medicine->batches()->count())->toBe(1);

    Livewire::test(CreateVaccinationSchedule::class)
        ->fillForm(['code' => 'ps1', 'name' => 'PRRS', 'medicine_id' => vaccine('PRRSV')->id, 'first_dose_age_days' => 28, 'repeat_interval_days' => 180])
        ->call('create')->assertHasNoFormErrors();
    expect(VaccinationSchedule::firstWhere('code', 'PS1'))->not->toBeNull();
});

it('signs visitors in and out, needing approval when conditions are not met', function () {
    $this->actingAs($manager = userWithRole('Farm Manager'));
    $base = ['visitor_name' => 'Ngozi', 'purpose' => 'Inspection', 'arrived_at' => now()->toDateTimeString(), 'health_declaration' => true, 'last_pig_contact_hours' => 100];

    Livewire::test(CreateBiosecurityVisit::class)->fillForm($base)->call('create')->assertHasNoFormErrors();
    expect(BiosecurityVisit::first()->approved_by)->toBeNull();

    Livewire::test(CreateBiosecurityVisit::class)->fillForm([...$base, 'last_pig_contact_hours' => 6])->call('create')->assertNotified('Not saved');
    expect(BiosecurityVisit::count())->toBe(1);

    Livewire::test(CreateBiosecurityVisit::class)->fillForm([...$base, 'last_pig_contact_hours' => 6, 'approved_by' => $manager->id])->call('create')->assertHasNoFormErrors();
    expect(BiosecurityVisit::count())->toBe(2);

    $visit = BiosecurityVisit::first();
    Livewire::test(ListBiosecurityVisits::class)
        ->callAction(TestAction::make('signout')->table($visit))
        ->assertNotified('Visitor signed out');
    expect($visit->fresh()->departed_at)->not->toBeNull();
});

it('records an inspection from the checklist', function () {
    $this->actingAs(owner());
    Livewire::test(CreateBiosecurityChecklistItem::class)->fillForm(['code' => 'foot', 'description' => 'Footbath at the gate'])->call('create')->assertHasNoFormErrors();
    Livewire::test(CreateBiosecurityChecklistItem::class)->fillForm(['code' => 'fence', 'description' => 'Fence intact'])->call('create')->assertHasNoFormErrors();

    $component = Livewire::test(CreateBiosecurityCheck::class)->fillForm(['checked_on' => now()->toDateString()]);
    $results = $component->get('data.results');
    $first = array_key_first($results);
    $component->set("data.results.{$first}.passed", false)->call('create')->assertHasNoFormErrors();

    $check = BiosecurityCheck::firstOrFail();
    expect($check->items_total)->toBe(2)->and($check->items_passed)->toBe(1);
});

it('shows alerts, reminders and the mortality analysis', function () {
    $this->actingAs(owner());
    $sick = register();
    app(ReportHealthEvent::class)($sick, HealthEventKind::Illness, HealthSeverity::Severe, now()->subDays(20));
    VaccinationSchedule::create(['code' => 'DUE', 'name' => 'Due jab', 'medicine_id' => vaccine('DUEV')->id, 'first_dose_age_days' => 5]);
    $young = register(['birth_date' => now()->subDays(30)->toDateString()]);
    $dead = register(['category_id' => categoryId('grower')]);
    app(RecordMortality::class)($dead, now(), cause('respiratory'));

    Livewire::test(HealthAlerts::class)->assertSee($sick->animal_number)->assertSee('open case');
    Livewire::test(VaccinationsDue::class)->assertSee($young->animal_number)->assertSee('Overdue');
    Livewire::test(MortalityAnalysis::class)->assertSee('Grower')->set('dimension', 'cause')->assertSee('Respiratory disease');
});

it('applies health permission defaults per role', function () {
    $animal = register();

    $this->actingAs(farmWorker());
    expect(auth()->user()->can('create', Treatment::class))->toBeTrue()
        ->and(auth()->user()->can('approve', WithdrawalPeriod::class))->toBeFalse();
    Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()])
        ->assertActionVisible('treat')->assertActionHidden('cull');

    $this->actingAs(userWithRole('Sales Officer'));
    expect(auth()->user()->can('viewAny', WithdrawalPeriod::class))->toBeTrue()
        ->and(auth()->user()->can('create', Treatment::class))->toBeFalse();
    Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()])->assertActionHidden('treat');

    $this->actingAs(userWithRole('Feed Mill Manager'));
    $this->get(TreatmentResource::getUrl('index'))->assertForbidden();
    $this->get(HealthAlerts::getUrl())->assertForbidden();

    $this->actingAs(userWithRole('Veterinarian'));
    expect(auth()->user()->can('approve', WithdrawalPeriod::class))->toBeTrue()
        ->and(auth()->user()->can('delete', Treatment::class))->toBeFalse();
});
