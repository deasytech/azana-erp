<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Support\DashboardFocus;
use App\Filament\Widgets\AnimalsByStatusChartWidget;
use App\Filament\Widgets\BornAliveChartWidget;
use App\Filament\Widgets\HerdByCategoryChartWidget;
use App\Filament\Widgets\SalesTrendChartWidget;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('gives each job its own focus', function (string $role, string $heading) {
    $this->actingAs($user = userWithRole($role));

    expect(DashboardFocus::for($user)['heading'])->toBe($heading);

    Livewire::test(Dashboard::class)->assertOk()->assertSee($heading);
})->with([
    ['Owner/Director', 'Management focus'],
    ['General Manager', 'Management focus'],
    ['Farm Manager', 'Farm supervision focus'],
    ['Store Officer', 'Stores focus'],
    ['Accountant', 'Finance focus'],
]);

it('shows only the standard dashboard to a role without a focus', function () {
    $this->actingAs($user = userWithRole('Farm Worker'));

    expect(DashboardFocus::for($user))->toBeNull();
    Livewire::test(Dashboard::class)->assertOk()->assertDontSee('Management focus')->assertDontSee('Stores focus');
});

it('leaves out figures and shortcuts the user may not see', function () {
    $this->actingAs(userWithRole('Store Officer'));
    $focus = Livewire::test(Dashboard::class)->instance()->focus();
    $keys = collect($focus['kpis'])->pluck('key');

    // Store officers hold no finance or herd permission beyond what the registry allows.
    expect($keys)->not->toContain('finance.net_margin_minor')
        ->and(collect($focus['links'])->pluck('label'))->not->toContain('Profitability');
});

it('shows nothing to a user with no role', function () {
    expect(DashboardFocus::for(User::factory()->create()))->toBeNull();
});

it('draws the dashboard charts only for data the user may see', function () {
    $this->actingAs(userWithRole('Store Officer'));
    expect(SalesTrendChartWidget::canView())->toBeTrue()
        ->and(BornAliveChartWidget::canView())->toBeFalse();

    $this->actingAs(owner());
    foreach ([SalesTrendChartWidget::class, BornAliveChartWidget::class, AnimalsByStatusChartWidget::class, HerdByCategoryChartWidget::class] as $widget) {
        Livewire::test($widget)->assertOk();
    }
    Livewire::test(Dashboard::class)->assertOk();
});
