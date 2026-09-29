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

it('returns 503 without leaking details when a dependency fails', function () {
    config([
        'session.driver' => 'database',
        'database.default' => 'broken',
        'database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x'],
    ]);
    app('db')->purge();

    try {
        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database.detail', 'Check failed');
    } finally {
        // Restore so RefreshDatabase tears down against the real test connection.
        config(['database.default' => 'sqlite']);
        app('db')->purge('broken');
    }
});
