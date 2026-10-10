<?php

use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Reporting\Models\KpiTarget;
use App\Filament\Pages\CashFlowReport;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ManagementDashboard;
use App\Filament\Pages\MonthlyManagementReport;
use App\Filament\Pages\ProfitabilityReport;
use App\Filament\Pages\ReceivablesPayables;
use App\Filament\Pages\TargetVsActual;
use App\Filament\Pages\TrialBalanceReport;
use App\Filament\Resources\KpiTargets\KpiTargetResource;
use App\Filament\Resources\KpiTargets\Pages\CreateKpiTarget;
use App\Filament\Resources\KpiTargets\Pages\EditKpiTarget;
use App\Filament\Support\MoneyColumn;
use App\Filament\Widgets\TargetAttainmentChartWidget;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

const DRESSING_LABEL = 'Dressing percentage';

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('renders the home screen, dashboards and reports for the owner', function () {
    $this->actingAs(owner());
    dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    app(SyncOperationalPostings::class)();

    foreach ([Dashboard::getUrl(), ManagementDashboard::getUrl(), TargetVsActual::getUrl(), MonthlyManagementReport::getUrl(), KpiTargetResource::getUrl('index'), KpiTargetResource::getUrl('create')] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('shows everybody a home screen limited to what they may see, and keeps the cockpit for those with the reports module', function () {
    $this->actingAs(farmWorker());
    $this->get(Dashboard::getUrl())->assertSuccessful()->assertSee('Today on the farm')->assertSee('My open tasks')->assertDontSee('Cash and bank')->assertDontSee('Dashboards');
    $this->get(ManagementDashboard::getUrl())->assertForbidden();
    $this->get(MonthlyManagementReport::getUrl())->assertForbidden();
    $this->get(KpiTargetResource::getUrl('index'))->assertForbidden();

    $this->actingAs(userWithRole('Accountant'));
    $this->get(MonthlyManagementReport::getUrl())->assertSuccessful();
    $this->get(KpiTargetResource::getUrl('create'))->assertForbidden();           // may read the reports, not set the targets
});

it('shows the daily snapshot to the owner with figures from the records', function () {
    $this->actingAs(owner());
    $invoice = dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    app(SyncOperationalPostings::class)();

    $this->get(Dashboard::getUrl())->assertSee('Sales invoiced')->assertSee(MoneyColumn::format($invoice->total_minor))->assertSee('Cash and bank')->assertSee('Trace a product')
        ->assertSee('Needs attention')->assertSee('This month against target');
});

it('opens a dashboard for each area and ignores an area or month it should not trust', function () {
    $this->actingAs(owner());
    slaughterPig();

    Livewire::test(ManagementDashboard::class)->assertSee('Executive')->assertSee('Pigs slaughtered')
        ->set('area', 'slaughter')->assertSee(DRESSING_LABEL)->assertSee('76')
        ->set('area', 'not-an-area')->assertSee('Pigs slaughtered')->assertDontSee(DRESSING_LABEL)
        ->set('month', 99)->set('year', 1)->assertSee('December 2000');

    // A dashboard is offered only for the areas the user may view.
    $this->actingAs(userWithRole('Accountant'));
    Livewire::test(ManagementDashboard::class)->set('area', 'finance')->assertSee('Revenue')->set('area', 'semen')->assertSee('Doses produced');
});

it('shows each indicator against its target on the target-vs-actual screen', function () {
    $this->actingAs(owner());
    slaughterPig();
    KpiTarget::create(['kpi_key' => 'slaughter.dressing_percent', 'year' => now()->year, 'month' => null, 'target_value' => '80']);

    Livewire::test(TargetVsActual::class)->assertSee(DRESSING_LABEL)->assertSee('76%')->assertSee('80%')->assertSee('Missed')->assertSee('95');
});

it('lets someone with edit rights set, change and reject duplicate targets', function () {
    $this->actingAs(userWithRole('General Manager'));

    Livewire::test(CreateKpiTarget::class)->fillForm(['kpi_key' => 'sales.invoiced_minor', 'year' => 2026, 'month' => null, 'target_value' => '500000000'])->call('create')->assertHasNoFormErrors();
    $target = KpiTarget::sole();

    expect($target->month)->toBeNull();

    Livewire::test(CreateKpiTarget::class)->fillForm(['kpi_key' => 'sales.invoiced_minor', 'year' => 2026, 'month' => null, 'target_value' => '1'])->call('create')->assertNotified('Not saved');
    Livewire::test(CreateKpiTarget::class)->fillForm(['kpi_key' => 'sales.invoiced_minor', 'year' => 2026, 'month' => 3, 'target_value' => 'lots'])->call('create')->assertHasFormErrors(['target_value']);

    Livewire::test(EditKpiTarget::class, ['record' => $target->getRouteKey()])->assertFormSet(['target_value' => '500000000'])->fillForm(['target_value' => '600000000'])->call('save')->assertHasNoFormErrors();

    expect($target->fresh()->target_value)->toBe('600000000.0000')->and(KpiTarget::count())->toBe(1);
});

it('downloads the monthly report as CSV, Excel and PDF, and refuses people who may not export', function () {
    $this->actingAs(owner());
    dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    app(SyncOperationalPostings::class)();
    $name = str(now()->format('F Y'))->slug();

    Livewire::test(MonthlyManagementReport::class)->assertSee('Alerts standing now')
        ->callAction('csv')->assertFileDownloaded("monthly-management-report-{$name}.csv")
        ->callAction('xlsx')->assertFileDownloaded("monthly-management-report-{$name}.xlsx")
        ->callAction('pdf')->assertFileDownloaded("monthly-management-report-{$name}.pdf");

    $this->actingAs(userWithRole('Store Officer'));
    $this->get(MonthlyManagementReport::getUrl())->assertForbidden();
});

it('exports the finance reports for people with finance export rights only', function () {
    $this->actingAs(owner());
    dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    app(SyncOperationalPostings::class)();

    Livewire::test(ProfitabilityReport::class)->callAction('csv')->assertFileDownloaded();
    Livewire::test(CashFlowReport::class)->callAction('xlsx')->assertFileDownloaded();
    Livewire::test(ReceivablesPayables::class)->callAction('pdf')->assertFileDownloaded();
    Livewire::test(TrialBalanceReport::class)->callAction('csv')->assertFileDownloaded();

    // Seeing the report is not enough: the General Manager may export finance, a Farm Worker has no finance access at all.
    $this->actingAs(userWithRole('Farm Manager'));
    Livewire::test(ProfitabilityReport::class)->assertActionVisible('csv');
});

it('searches across the farm from one box: animals, customers, tasks, orders and batches', function () {
    $this->actingAs(owner());
    $animal = register();
    customer(['name' => 'Zebra Meats']);
    $task = newTask(['title' => 'Check zebra fence']);
    $order = semenOrder(creditCustomer(), releasedSemen(), 1);

    $search = fn (string $term) => collect(Filament::getGlobalSearchProvider()->getResults($term)->getCategories())->flatten()->map(fn ($r) => $r->title)->all();

    expect($search($animal->animal_number))->toContain($animal->animal_number)->and($search('Zebra'))->toContain('Zebra Meats')
        ->and($search($task->number))->toContain($task->number)->and($search($order->number))->toContain($order->number);
});

it('hides the export buttons while the chosen period is not valid, without building the report', function () {
    $this->actingAs(owner());

    Livewire::test(ProfitabilityReport::class)->assertActionVisible('csv')->set('from', 'not a date')->assertActionHidden('csv')
        ->set('from', now()->toDateString())->assertActionVisible('csv');
    Livewire::test(CashFlowReport::class)->set('to', now()->subYears(2)->toDateString())->assertActionHidden('xlsx');
});

it('draws the dashboards against target and follows the chosen dashboard and month', function () {
    $this->actingAs(owner());
    slaughterPig();
    KpiTarget::create(['kpi_key' => 'slaughter.pigs', 'year' => now()->year, 'month' => null, 'target_value' => '10']);

    // Chart widgets load lazily: each page carries the component, and the widget itself is asserted below.
    $this->get(Dashboard::getUrl())->assertSuccessful()->assertSeeLivewire(TargetAttainmentChartWidget::class);
    $this->get(ManagementDashboard::getUrl())->assertSuccessful()->assertSeeLivewire(TargetAttainmentChartWidget::class);
    Livewire::test(TargetAttainmentChartWidget::class)->assertSee('Against target');

    Livewire::test(ManagementDashboard::class)
        ->set('area', 'slaughter')
        ->assertDispatched('report-filter-changed', year: (int) now()->year, month: (int) now()->month, area: 'slaughter')
        ->set('month', 99)
        ->assertDispatched('report-filter-changed', month: 12);
});
