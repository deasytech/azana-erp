<?php

use App\Domain\Animal\Actions\AddAnimalIdentifier;
use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Actions\RegisterAnimal;
use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\Building;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Farm\Models\ProductionUnit;
use App\Enums\AnimalStatus;
use App\Enums\IdentifierType;
use App\Enums\LookupCategory;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Animals\Pages\CreateAnimal;
use App\Filament\Resources\Animals\Pages\EditAnimal;
use App\Filament\Resources\Animals\Pages\ListAnimals;
use App\Filament\Resources\Animals\Pages\ViewAnimal;
use App\Filament\Resources\Animals\RelationManagers\IdentifiersRelationManager;
use App\Filament\Resources\Animals\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Animals\RelationManagers\WeightsRelationManager;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function uiCategory(string $code): int
{
    return LookupValue::where('category', LookupCategory::AnimalCategory->value)->where('code', $code)->value('id');
}

function uiPen(string $code = 'UP1', ?int $capacity = null): Pen
{
    $building = Building::firstOrCreate(['code' => 'UIB'], [
        'production_unit_id' => ProductionUnit::firstWhere('code', 'PIG')->id,
        'type_id' => LookupValue::where('category', LookupCategory::BuildingType->value)->value('id'), 'name' => 'UI building',
    ]);

    return Pen::create([
        'building_id' => $building->id, 'code' => $code, 'capacity' => $capacity,
        'purpose_id' => LookupValue::where('category', LookupCategory::PenPurpose->value)->value('id'),
    ]);
}

function uiAnimal(array $extra = []): Animal
{
    return app(RegisterAnimal::class)(array_merge(
        ['sex' => 'female', 'category_id' => uiCategory('sow'), 'source' => 'born_on_farm'], $extra,
    ));
}

it('renders the animal pages for the owner', function () {
    $this->actingAs(owner());
    $animal = uiAnimal(['pen_id' => uiPen()->id]);
    app(RecordWeight::class)($animal, '30.5');

    $this->get(AnimalResource::getUrl('index'))->assertOk()->assertSee($animal->animal_number);
    $this->get(AnimalResource::getUrl('create'))->assertOk();
    $this->get(AnimalResource::getUrl('view', ['record' => $animal]))->assertOk()->assertSee('Lifecycle history')->assertSee('30.50 kg');
    $this->get(AnimalResource::getUrl('edit', ['record' => $animal]))->assertOk();
});

it('lists animals with their position and latest weight', function () {
    $this->actingAs(owner());
    $animal = uiAnimal(['pen_id' => uiPen('LISTPEN')->id]);
    app(RecordWeight::class)($animal, '45');

    Livewire::test(ListAnimals::class)
        ->assertCanSeeTableRecords([$animal])
        ->assertSee('LISTPEN')
        ->assertSee('45.00');
});

it('registers an animal through the form with identifiers, parentage and placement', function () {
    $this->actingAs(owner());
    $boar = uiAnimal(['sex' => 'male', 'category_id' => uiCategory('boar')]);
    $pen = uiPen();

    Livewire::test(CreateAnimal::class)
        ->fillForm([
            'sex' => 'female', 'category_id' => uiCategory('gilt'), 'source' => 'purchased', 'source_name' => 'Big Farm Ltd',
            'purchase_price_minor' => '150000.50', 'sire_id' => $boar->id, 'pen_id' => $pen->id,
            'identifiers' => [['type' => 'ear_tag', 'value' => 'ui-77']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $animal = Animal::where('category_id', uiCategory('gilt'))->firstOrFail();

    expect($animal->animal_number)->toBe('IPA-GILT-0001')
        ->and($animal->purchase_price_minor)->toBe(15000050)
        ->and($animal->parentage->sire->is($boar))->toBeTrue()
        ->and($animal->currentPen->is($pen))->toBeTrue()
        ->and($animal->identifiers->first()->value)->toBe('UI-77');
});

it('shows business-rule failures as notifications when registering', function () {
    $this->actingAs(owner());

    Livewire::test(CreateAnimal::class)
        ->fillForm(['sex' => 'female', 'category_id' => uiCategory('boar'), 'source' => 'born_on_farm'])
        ->call('create')
        ->assertNotified('Not saved');

    expect(Animal::count())->toBe(0);
});

it('edits details and parentage but never the number or sex', function () {
    $this->actingAs(owner());
    $sire = uiAnimal(['sex' => 'male', 'category_id' => uiCategory('boar')]);
    $animal = uiAnimal();

    Livewire::test(EditAnimal::class, ['record' => $animal->getRouteKey()])
        ->assertFormFieldDisabled('animal_number')
        ->assertFormFieldDisabled('sex')
        ->fillForm(['notes' => 'Good mother', 'sire_id' => $sire->id, 'sire_note' => 'Line X'])
        ->call('save')
        ->assertHasNoFormErrors();

    $animal->refresh();
    expect($animal->notes)->toBe('Good mother')
        ->and($animal->parentage->sire->is($sire))->toBeTrue()
        ->and($animal->animal_number)->toBe('IPA-SOW-0001');
});

it('moves, weighs and changes status from the profile page', function () {
    $this->actingAs(owner());
    $pen = uiPen('DEST');
    $animal = uiAnimal();

    $page = Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()]);

    $page->callAction('move', ['destination' => "pen:{$pen->id}", 'moved_at' => now()->addMinute()->toDateTimeString(), 'notes' => 'To grower'])
        ->assertNotified('Animal moved');
    expect($animal->fresh()->currentPen->is($pen))->toBeTrue();

    $page->callAction('weigh', ['weight_kg' => 55.5, 'weighed_at' => now()->addMinutes(2)->toDateTimeString()])
        ->assertNotified('Weight recorded');
    expect($animal->fresh()->latestWeight()->weight_kg)->toBe('55.50');

    $page->callAction('status', ['status' => 'sold', 'reason' => 'Sold at market', 'changed_at' => now()->addMinutes(3)->toDateTimeString()])
        ->assertNotified('Status changed');
    expect($animal->fresh()->status)->toBe(AnimalStatus::Sold);

    $page->assertActionHidden('move')->assertActionHidden('weigh')->assertActionHidden('status');
});

it('surfaces movement rule violations without saving anything', function () {
    $this->actingAs(owner());
    $full = uiPen('FULL', 1);
    uiAnimal(['pen_id' => $full->id]);
    $animal = uiAnimal();

    Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()])
        ->callAction('move', ['destination' => "pen:{$full->id}", 'moved_at' => now()->toDateTimeString()])
        ->assertNotified('Not saved');

    expect($animal->fresh()->current_pen_id)->toBeNull()->and($animal->movements()->count())->toBe(0);
});

it('manages identifiers from the relation manager', function () {
    $this->actingAs(owner());
    $animal = uiAnimal();
    $manager = Livewire::test(IdentifiersRelationManager::class, ['ownerRecord' => $animal, 'pageClass' => ViewAnimal::class]);

    $manager->callAction(TestAction::make('create')->table(), ['type' => 'rfid', 'value' => 'abc123'])->assertNotified();
    expect($animal->identifiers()->first()->value)->toBe('ABC123');

    $manager->callAction(TestAction::make('create')->table(), ['type' => 'rfid', 'value' => 'abc123'])->assertNotified('Not saved');
    expect($animal->identifiers()->count())->toBe(1);

    $identifier = $animal->identifiers()->first();
    Livewire::test(IdentifiersRelationManager::class, ['ownerRecord' => $animal, 'pageClass' => ViewAnimal::class])
        ->callAction(TestAction::make('retire')->table($identifier), ['reason' => 'Lost']);
    expect($identifier->fresh()->isRetired())->toBeTrue();
});

it('records and voids weights from the relation manager', function () {
    $this->actingAs(owner());
    $animal = uiAnimal();
    $manager = Livewire::test(WeightsRelationManager::class, ['ownerRecord' => $animal, 'pageClass' => ViewAnimal::class]);

    $manager->callAction(TestAction::make('create')->table(), ['weight_kg' => 20, 'weighed_at' => now()->toDateTimeString()]);
    $weight = $animal->weights()->first();
    expect($weight->weight_kg)->toBe('20.00');

    $manager->callAction(TestAction::make('void')->table($weight), ['reason' => 'Typo']);
    expect($weight->fresh()->isVoided())->toBeTrue();
});

it('applies the animal permission defaults per role', function () {
    $animal = uiAnimal();

    $this->actingAs(farmWorker());
    expect(auth()->user()->can('create', Animal::class))->toBeTrue()
        ->and(auth()->user()->can('update', $animal))->toBeFalse()
        ->and(auth()->user()->can('approve', $animal))->toBeFalse()
        ->and(auth()->user()->can('delete', $animal))->toBeFalse();
    Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()])
        ->assertActionVisible('move')->assertActionHidden('status');

    $this->actingAs(userWithRole('Sales Officer'));
    Livewire::test(ViewAnimal::class, ['record' => $animal->getRouteKey()])
        ->assertActionHidden('move')->assertActionHidden('weigh');
    expect(auth()->user()->can('create', Animal::class))->toBeFalse();

    $this->actingAs(userWithRole('Feed Mill Manager'));
    $this->get(AnimalResource::getUrl('index'))->assertForbidden();

    $this->actingAs(userWithRole('Farm Manager'));
    expect(auth()->user()->can('approve', $animal))->toBeTrue()
        ->and(auth()->user()->can('delete', $animal))->toBeFalse();
});

it('finds animals by identifier in the global search', function () {
    $this->actingAs(owner());
    $animal = uiAnimal();
    app(AddAnimalIdentifier::class)($animal, IdentifierType::EarTag, 'FINDME-42');

    $results = Filament::getGlobalSearchProvider()->getResults('FINDME-42');
    $titles = collect($results->getCategories())->flatten(1)->map(fn ($r) => $r->title)->all();

    expect($titles)->toContain($animal->animal_number);
});

it('looks animals up over HTTP for scanners and browsers', function () {
    $animal = uiAnimal(['pen_id' => uiPen()->id]);
    app(AddAnimalIdentifier::class)($animal, IdentifierType::Barcode, 'BC-555');

    $this->getJson(route('animals.lookup', 'BC-555'))->assertUnauthorized();
    $this->get(route('animals.lookup', 'BC-555'))->assertRedirect(route('filament.admin.auth.login'));

    $this->actingAs(farmWorker());
    $this->getJson(route('animals.lookup', 'BC-555'))
        ->assertOk()
        ->assertJsonPath('animal_number', $animal->animal_number)
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('position', 'UP1 (UI building)');
    $this->getJson(route('animals.lookup', $animal->public_id))->assertOk();
    $this->getJson(route('animals.lookup', 'NOPE'))->assertNotFound();
    $this->get(route('animals.lookup', $animal->qrPayload()))->assertRedirect(AnimalResource::getUrl('view', ['record' => $animal]));

    $this->actingAs(userWithRole('Feed Mill Manager'));
    $this->getJson(route('animals.lookup', 'BC-555'))->assertForbidden();
});

it('stores animal photos privately, validates uploads, and lets editors remove them', function () {
    Storage::fake(config('filesystems.default'));
    $this->actingAs(owner());
    $animal = uiAnimal();
    $manager = fn () => Livewire::test(PhotosRelationManager::class, ['ownerRecord' => $animal, 'pageClass' => ViewAnimal::class]);

    $manager()->callAction(TestAction::make('create')->table(), ['path' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), 'caption' => 'bad'])
        ->assertHasFormErrors(['path']);
    expect($animal->photos()->count())->toBe(0);

    $manager()->callAction(TestAction::make('create')->table(), ['path' => UploadedFile::fake()->image('pig.jpg'), 'caption' => 'Left side'])
        ->assertHasNoFormErrors();

    $photo = $animal->photos()->firstOrFail();
    Storage::disk($photo->disk)->assertExists($photo->path);
    expect($photo->uploaded_by)->toBe(auth()->id())
        ->and($photo->path)->toStartWith('animal-photos/')
        ->and(basename($photo->path))->not->toContain('pig');

    $worker = farmWorker();
    expect($worker->can('delete', $photo))->toBeFalse()
        ->and(userWithRole('Breeding Manager')->can('delete', $photo))->toBeTrue();
});
