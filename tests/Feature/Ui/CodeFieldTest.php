<?php

use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\LookupValue;
use App\Enums\LookupCategory;
use App\Filament\Resources\Breeds\Pages\CreateBreed;
use App\Filament\Resources\Breeds\Pages\EditBreed;
use App\Filament\Resources\LookupValues\Pages\CreateLookupValue;
use App\Filament\Support\CodeField;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->actingAs(owner());
});

it('works out the next free code from the highest number in use', function () {
    $next = fn () => CodeField::next('breeds', 'TST');
    expect($next())->toBe('TST-0001');

    Breed::create(['code' => 'TST-0001', 'name' => 'A', 'species' => 'pig']);
    Breed::create(['code' => 'TST-0009', 'name' => 'B', 'species' => 'pig']);
    Breed::create(['code' => 'OTHER', 'name' => 'C', 'species' => 'pig']);

    expect($next())->toBe('TST-0010');
});

it('fills a new record with a free code and offers an icon for another', function () {
    $component = Livewire::test(CreateBreed::class);
    $first = $component->get('data.code');
    expect($first)->toMatch('/^BRD-\d{4}$/');

    $component->fillForm(['code' => 'X'])->callFormComponentAction('code', 'generateCode');
    expect($component->get('data.code'))->toMatch('/^BRD-\d{4}$/');
});

it('keeps the generated code editable and still rejects a duplicate', function () {
    $breed = Breed::create(['code' => 'DUP-1', 'name' => 'A', 'species' => 'pig']);

    Livewire::test(CreateBreed::class)
        ->fillForm(['code' => 'dup-1', 'name' => 'B', 'species' => 'pig'])
        ->call('create')
        ->assertHasFormErrors(['code']);

    Livewire::test(CreateBreed::class)
        ->fillForm(['name' => 'Fresh', 'species' => 'pig'])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(Breed::where('name', 'Fresh')->value('code'))->toMatch('/^BRD-\d{4}$/');
});

it('does not offer the generator when editing', function () {
    $breed = Breed::create(['code' => 'KEEP-1', 'name' => 'A', 'species' => 'pig']);

    Livewire::test(EditBreed::class, ['record' => $breed->getRouteKey()])
        ->assertFormSet(['code' => 'KEEP-1'])
        ->assertFormComponentActionHidden('code', 'generateCode');
});

it('makes a lookup code from the name', function () {
    expect(CodeField::slug('Weaner pen (large)'))->toBe('weaner_pen_large');

    Livewire::test(CreateLookupValue::class)
        ->fillForm(['category' => LookupCategory::cases()[0]->value, 'name' => 'Cull reason A'])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(LookupValue::where('name', 'Cull reason A')->value('code'))->toBe('cull_reason_a');
});
