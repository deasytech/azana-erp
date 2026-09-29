<?php

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Breeding\Models\HeatEvent;
use App\Domain\Litter\Actions\RecordLitterLoss;
use App\Domain\Litter\Actions\RegisterLitterPiglets;
use App\Domain\Litter\Actions\WeanLitter;
use App\Domain\Litter\Models\Litter;
use App\Enums\AnimalStatus;
use App\Enums\ServiceOutcome;
use App\Filament\Pages\BreedingCalendar;
use App\Filament\Resources\Animals\Pages\ViewAnimal;
use App\Filament\Resources\Animals\RelationManagers\LittersRelationManager;
use App\Filament\Resources\BreedingServices\BreedingServiceResource;
use App\Filament\Resources\BreedingServices\Pages\CreateBreedingService;
use App\Filament\Resources\BreedingServices\Pages\ViewBreedingService;
use App\Filament\Resources\HeatEvents\HeatEventResource;
use App\Filament\Resources\HeatEvents\Pages\CreateHeatEvent;
use App\Filament\Resources\Litters\LitterResource;
use App\Filament\Resources\Litters\Pages\ListLitters;
use App\Filament\Resources\Litters\Pages\ViewLitter;
use App\Filament\Resources\Litters\RelationManagers\PigletsRelationManager;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('renders every breeding page for the owner', function () {
    $this->actingAs(owner());
    $sow = register();
    $service = serve($sow, 114);
    $litter = farrow(register());

    $this->get(BreedingServiceResource::getUrl('index'))->assertOk()->assertSee($sow->animal_number);
    $this->get(BreedingServiceResource::getUrl('create'))->assertOk();
    $this->get(BreedingServiceResource::getUrl('view', ['record' => $service]))->assertOk()->assertSee('Expected dates');
    $this->get(HeatEventResource::getUrl('index'))->assertOk();
    $this->get(HeatEventResource::getUrl('create'))->assertOk();
    $this->get(LitterResource::getUrl('index'))->assertOk()->assertSee($litter->litter_number);
    $this->get(LitterResource::getUrl('view', ['record' => $litter]))->assertOk()->assertSee('Performance');
    $this->get(BreedingCalendar::getUrl())->assertOk();
});

it('records a service through the form and shows rule violations as notifications', function () {
    $this->actingAs(owner());
    $sow = register();
    $boar = boar();

    Livewire::test(CreateBreedingService::class)
        ->fillForm(['sow_id' => $sow->id, 'method' => 'natural', 'serviced_on' => now()->subDay()->toDateString(), 'boar_id' => $boar->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(BreedingService::first()->boar->is($boar))->toBeTrue();

    Livewire::test(CreateBreedingService::class)
        ->fillForm(['sow_id' => register()->id, 'method' => 'natural', 'serviced_on' => now()->toDateString()])
        ->call('create')
        ->assertNotified('Not saved');

    expect(BreedingService::count())->toBe(1);
});

it('records heat through the form', function () {
    $this->actingAs(owner());
    $sow = register();

    Livewire::test(CreateHeatEvent::class)
        ->fillForm(['sow_id' => $sow->id, 'detected_on' => now()->toDateString(), 'notes' => 'Standing'])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateHeatEvent::class)
        ->fillForm(['sow_id' => $sow->id, 'detected_on' => now()->toDateString()])
        ->call('create')
        ->assertNotified('Not saved');

    expect(HeatEvent::count())->toBe(1);
});

it('walks a service through check and farrowing from its page', function () {
    $this->actingAs(owner());
    $sow = register();
    $service = serve($sow, 114);
    $page = Livewire::test(ViewBreedingService::class, ['record' => $service->getRouteKey()]);

    $page->callAction('check', ['result' => 'positive', 'method' => 'ultrasound', 'checked_on' => now()->subDays(80)->toDateString()])
        ->assertNotified('Pregnancy check recorded');
    expect($service->fresh()->outcome)->toBe(ServiceOutcome::Pregnant);

    $page->callAction('farrow', ['farrowed_on' => now()->toDateString(), 'born_alive' => 11, 'stillborn' => 1, 'mummified' => 0])
        ->assertNotified();

    $litter = Litter::firstOrFail();
    expect($litter->sow->is($sow))->toBeTrue()
        ->and($litter->farrowing->total_born)->toBe(12)
        ->and($service->fresh()->outcome)->toBe(ServiceOutcome::Farrowed);

    $page->assertActionHidden('check')->assertActionHidden('farrow')->assertActionHidden('abort');
});

it('records an abortion from the service page', function () {
    $this->actingAs(owner());
    $service = serve(register(), 60);

    Livewire::test(ViewBreedingService::class, ['record' => $service->getRouteKey()])
        ->callAction('abort', ['occurred_on' => now()->toDateString(), 'reason' => 'Fever'])
        ->assertNotified('Abortion recorded');

    expect($service->fresh()->outcome)->toBe(ServiceOutcome::Aborted);
});

it('shows a farrowing rule violation instead of saving', function () {
    $this->actingAs(owner());
    $service = serve(register(), 114);

    Livewire::test(ViewBreedingService::class, ['record' => $service->getRouteKey()])
        ->callAction('farrow', ['farrowed_on' => now()->subDays(200)->toDateString(), 'born_alive' => 5, 'stillborn' => 0, 'mummified' => 0])
        ->assertNotified('Not saved');

    expect(Litter::count())->toBe(0);
});

it('records a farrowing for a sow without a service from the litters list', function () {
    $this->actingAs(owner());
    $sow = register();

    Livewire::test(ListLitters::class)
        ->callAction('farrow', ['sow_id' => $sow->id, 'farrowed_on' => now()->toDateString(), 'born_alive' => 9, 'stillborn' => 0, 'mummified' => 1, 'total_birth_weight_kg' => 13.5])
        ->assertNotified();

    $litter = Litter::firstOrFail();
    expect($litter->farrowing->total_born)->toBe(10)
        ->and($litter->farrowing->total_birth_weight_kg)->toBe('13.50');
});

it('registers piglets, records a loss and weans from the litter page', function () {
    $this->actingAs(owner());
    $litter = farrow(register(), ['farrowed_on' => now()->subDays(28)->startOfDay()]);
    $pen = newPen('WEAN1');
    $page = Livewire::test(ViewLitter::class, ['record' => $litter->getRouteKey()]);

    $page->callAction('piglets', ['piglets' => [
        ['sex' => 'female', 'birth_weight_kg' => 1.4, 'ear_tag' => 'LT-1'],
        ['sex' => 'male', 'birth_weight_kg' => 1.5, 'ear_tag' => ''],
    ]])->assertNotified('Piglets registered');
    expect($litter->piglets()->count())->toBe(2);

    $tracked = Animal::firstWhere('animal_number', 'IPA-PIGLET-0002');
    $page->callAction('loss', ['count' => 1, 'occurred_on' => now()->subDays(10)->toDateString(), 'cause' => 'Crushed', 'animal_id' => $tracked->id])
        ->assertNotified('Loss recorded');
    expect($tracked->fresh()->status)->toBe(AnimalStatus::Dead);

    $page->callAction('wean', ['weaned_on' => now()->toDateString(), 'weaned_count' => 9, 'total_weight_kg' => 63, 'pen_id' => $pen->id])
        ->assertNotified('Litter weaned');

    $alive = Animal::firstWhere('animal_number', 'IPA-PIGLET-0001');
    expect($litter->fresh()->isSuckling())->toBeFalse()
        ->and($alive->fresh()->category->code)->toBe('weaner')
        ->and($alive->fresh()->currentPen->is($pen))->toBeTrue();

    $page->assertActionHidden('wean')->assertActionHidden('loss')->assertActionHidden('piglets');
});

it('shows litter KPIs on the litter page', function () {
    $this->actingAs(owner());
    $litter = farrow(register(), ['farrowed_on' => now()->subDays(28)->startOfDay(), 'total_birth_weight_kg' => '15.00']);
    app(RecordLitterLoss::class)($litter, 2, now()->subDays(20)->startOfDay());
    app(WeanLitter::class)($litter, now()->startOfDay(), 8, '56.00');

    Livewire::test(ViewLitter::class, ['record' => $litter->getRouteKey()])
        ->assertSee('20.00%')   // pre-weaning mortality
        ->assertSee('80.00%')   // weaning
        ->assertSee('1.50')     // avg birth weight
        ->assertSee('7.00');    // avg weaning weight
});

it('lists tracked piglets on the litter page', function () {
    $this->actingAs(owner());
    $litter = farrow(register());
    $piglet = app(RegisterLitterPiglets::class)($litter, [['sex' => 'male', 'birth_weight_kg' => '1.30']])->first();

    Livewire::test(PigletsRelationManager::class, ['ownerRecord' => $litter, 'pageClass' => ViewLitter::class])
        ->assertSee($piglet->animal_number)
        ->assertSee('1.30');
});

it('shows a sow\'s reproduction summary and litters on her profile, but not a boar\'s', function () {
    $this->actingAs(owner());
    $sow = register();
    farrow($sow, ['farrowed_on' => now()->subDays(30)->startOfDay()]);

    Livewire::test(ViewAnimal::class, ['record' => $sow->getRouteKey()])
        ->assertSee('Reproduction')->assertSee('Lactating')->assertSee('Litters (parity)');

    Livewire::test(ViewAnimal::class, ['record' => boar()->getRouteKey()])->assertDontSee('Reproduction');
    expect(LittersRelationManager::canViewForRecord($sow, ViewAnimal::class))->toBeTrue()
        ->and(LittersRelationManager::canViewForRecord(boar(), ViewAnimal::class))->toBeFalse();
});

it('shows upcoming events on the breeding calendar page', function () {
    $this->actingAs(owner());
    $sow = register();
    serve($sow, 20);

    Livewire::test(BreedingCalendar::class)
        ->assertSee('Pregnancy check due')
        ->assertSee($sow->animal_number)
        ->set('to', now()->addDays(120)->toDateString())
        ->assertSee('Farrowing expected');
});

it('applies the breeding permission defaults per role', function () {
    $service = serve(register(), 10);

    $this->actingAs(farmWorker());
    expect(auth()->user()->can('create', BreedingService::class))->toBeTrue()
        ->and(auth()->user()->can('delete', $service))->toBeFalse();
    $this->get(BreedingServiceResource::getUrl('index'))->assertOk();

    $this->actingAs(userWithRole('Semen Laboratory Manager'));
    expect(auth()->user()->can('viewAny', BreedingService::class))->toBeTrue()
        ->and(auth()->user()->can('create', BreedingService::class))->toBeFalse();
    Livewire::test(ViewBreedingService::class, ['record' => $service->getRouteKey()])
        ->assertActionHidden('check')->assertActionHidden('farrow');

    $this->actingAs(userWithRole('Sales Officer'));
    $this->get(BreedingServiceResource::getUrl('index'))->assertForbidden();
    $this->get(LitterResource::getUrl('index'))->assertForbidden();
    $this->get(BreedingCalendar::getUrl())->assertForbidden();

    $this->actingAs(userWithRole('Breeding Manager'));
    expect(auth()->user()->can('create', BreedingService::class))->toBeTrue()
        ->and(auth()->user()->can('delete', $service))->toBeFalse();
});
