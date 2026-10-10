<?php

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Sales\Models\Customer;
use App\Domain\System\Actions\ResetToLiveData;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\DataMode;
use App\Filament\Pages\GoLiveDataReset;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

function practiceRecords(): void
{
    register();
    customer();
    supplier();
    receiveStock(stockItem('MAIZE'), '100', 35000);
}

function setMode(DataMode $mode): void
{
    app(ResolveSettings::class)->set('system.data_mode', $mode->value);
}

it('names every table as kept or wiped, so a new table cannot be forgotten', function () {
    $tables = collect(Schema::getTables())->pluck('name')->all();
    $named = [...ResetToLiveData::KEEP, ...ResetToLiveData::WIPE];

    expect(array_values(array_diff($tables, $named)))->toBe([], 'tables the reset does not know about')
        ->and(array_values(array_intersect(ResetToLiveData::KEEP, ResetToLiveData::WIPE)))->toBe([])
        ->and(array_values(array_diff($named, $tables)))->toBe([], 'tables named but not in the schema');
});

it('refuses to run once the system is live, and deletes nothing', function () {
    practiceRecords();

    expect(app(ResetToLiveData::class)->mode())->toBe(DataMode::Live);

    expect(fn () => app(ResetToLiveData::class)(owner(), false))->toThrow(DomainException::class, 'live mode')
        ->and(Animal::count())->toBe(1)->and(Customer::count())->toBe(1);
});

it('clears the practice records, keeps the set-up, puts the standard stock items back and switches to live', function () {
    setMode(DataMode::Demo);
    practiceRecords();
    $owner = owner();
    $roles = Role::count();
    $items = InventoryItem::whereIn('category', ['semen', 'meat'])->count();

    $result = app(ResetToLiveData::class)($owner, false);

    expect($result['rows'])->toBeGreaterThan(3)
        ->and(Animal::count())->toBe(0)->and(Customer::count())->toBe(0)
        ->and(InventoryItem::where('code', 'MAIZE')->exists())->toBeFalse()
        ->and(InventoryItem::whereIn('category', ['semen', 'meat'])->count())->toBe($items)
        ->and(Role::count())->toBe($roles)->and(User::whereKey($owner->id)->exists())->toBeTrue()
        ->and(app(ResolveSettings::class)->get('system.data_mode'))->toBe('live')
        ->and(app(ResetToLiveData::class)->allowed())->toBeFalse()
        ->and(AuditLog::where('event', 'system.data_reset')->count())->toBe(1);

    // Numbering starts again from the beginning.
    expect(register()->animal_number)->toEndWith('0001');
});

it('can also remove the demo accounts but never the person doing the reset', function () {
    setMode(DataMode::Demo);
    $me = owner(['email' => 'me@azana.test']);
    $other = farmWorker();
    $other->update(['email' => 'worker@azana.test']);
    $real = userWithRole('Accountant', ['email' => 'finance@realfarm.com']);

    expect(app(ResetToLiveData::class)->demoAccounts($me))->toBe(1);

    $result = app(ResetToLiveData::class)($me, false, true);

    expect($result['users'])->toBe(1)
        ->and(User::whereKey($me->id)->exists())->toBeTrue()->and(User::whereKey($other->id)->exists())->toBeFalse()->and(User::whereKey($real->id)->exists())->toBeTrue();
});

it('opens only for the Owner', function () {
    $this->actingAs(owner());
    expect(GoLiveDataReset::canAccess())->toBeTrue();

    $this->actingAs(userWithRole('General Manager'));
    expect(GoLiveDataReset::canAccess())->toBeFalse();
});

it('shows what will be deleted and needs the word RESET to go ahead', function () {
    setMode(DataMode::Demo);
    practiceRecords();
    $this->actingAs(owner());

    Livewire::test(GoLiveDataReset::class)->assertSee('Will be deleted')->assertSee('Animals')
        ->callAction('reset', ['backup' => false, 'accounts' => false, 'confirm' => 'nope'])->assertHasActionErrors(['confirm']);
    expect(Animal::count())->toBe(1);

    Livewire::test(GoLiveDataReset::class)->callAction('reset', ['backup' => false, 'accounts' => false, 'confirm' => 'RESET'])->assertHasNoActionErrors();
    expect(Animal::count())->toBe(0)->and(app(ResetToLiveData::class)->allowed())->toBeFalse();
});

it('hides the button once the system is live', function () {
    $this->actingAs(owner());

    Livewire::test(GoLiveDataReset::class)->assertSee('live mode')->assertActionHidden('reset');
});
