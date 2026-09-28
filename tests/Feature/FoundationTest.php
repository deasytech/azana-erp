<?php

use App\Domain\System\Exceptions\DomainException;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it('boots the application', function () {
    $this->get('/up')->assertOk();
});

it('reports a healthy system', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.storage.ok', true);
});

it('loads the Filament admin panel login', function () {
    $this->get('/admin/login')->assertOk();
});

it('runs Livewire components', function () {
    Livewire::test(Login::class)->assertOk();
});

it('renders domain exceptions as JSON 422', function () {
    Route::get('/_boom', fn () => throw new DomainException('Nope', 'rule_broken'));

    $this->getJson('/_boom')
        ->assertStatus(422)
        ->assertJson(['message' => 'Nope', 'code' => 'rule_broken']);
});
