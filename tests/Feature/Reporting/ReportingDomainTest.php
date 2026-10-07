<?php

use App\Domain\Finance\Actions\GetProfitability;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Reporting\Actions\GetKpis;
use App\Domain\Reporting\Actions\GetMonthlyReport;
use App\Domain\Reporting\Actions\GetOwnerSnapshot;
use App\Domain\Reporting\Actions\GetTargetVsActual;
use App\Domain\Reporting\Actions\SetKpiTarget;
use App\Domain\Reporting\Exports\ExportReport;
use App\Domain\Reporting\Exports\ReportDocument;
use App\Domain\Reporting\Exports\ReportDocuments;
use App\Domain\Reporting\KpiRegistry;
use App\Domain\Reporting\Models\KpiTarget;
use App\Domain\Slaughter\Actions\GetSlaughterYield;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\Module;
use App\Models\User;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

function kpiValues(?User $for = null, int $daysBack = 40): array
{
    return collect(app(GetKpis::class)(now()->subDays($daysBack), now(), $for))->map(fn ($k) => $k['value'])->all();
}

it('registers every indicator against a real dashboard and a real module', function () {
    foreach (KpiRegistry::all() as $key => $k) {
        expect(KpiRegistry::AREAS)->toHaveKey($k['area'])->and(Module::tryFrom($k['module']))->not->toBeNull()
            ->and($k['unit'])->toBeIn(['count', 'kg', 'doses', 'percent', 'money'])->and($k['better'])->toBeIn(['higher', 'lower', 'none']);
    }

    // The owner gets a value slot for every one of them (null where there is nothing yet), and no indicator is made up.
    expect(array_keys(kpiValues(owner())))->toBe(array_keys(KpiRegistry::all()));
});

it('works the indicators out from the farm\'s own records', function () {
    $batch = scenario();                                                     // 100 placed, 2 died, 98 left; cost 235,750,000
    $run = completedFeedRun(millFixture());                                  // 990 kg made
    $semen = releasedSemen();
    $invoice = dispatched(semenOrder(creditCustomer(), $semen, 10));         // 15,000,000
    pay($invoice->customer, 6000000);
    app(SyncOperationalPostings::class)();
    slaughterPig();

    $v = kpiValues();

    expect($v['production.growing_pigs'])->toBe(98)->and($v['production.active_batches'])->toBe(1)->and($v['production.mortality_percent'])->toBe('2.00')->and($v['production.cost_per_pig_minor'])->toBe(2405612)
        ->and($v['feed.produced_kg'])->toBe('990.00')->and($v['feed.cost_per_kg_minor'])->toBe($run->cost_per_kg_minor)
        ->and($v['semen.doses'])->toBe((int) $semen->refresh()->doses_produced)
        ->and($v['sales.invoiced_minor'])->toBe(15000000)->and($v['sales.receipts_minor'])->toBe(6000000)->and($v['sales.receivables_minor'])->toBe(9000000)->and($v['sales.overdue_minor'])->toBe(0)
        ->and($v['finance.revenue_minor'])->toBe(15000000)->and($v['finance.cash_minor'])->toBe(6000000)->and($v['finance.payables_minor'])->toBe(0)
        ->and($v['slaughter.pigs'])->toBe(1)->and($v['slaughter.dressing_percent'])->toBe('76.00');
});

it('gives the same figure on every screen, because they all read the same calculation', function () {
    slaughterPig();
    $pay = dispatched(semenOrder(creditCustomer(), releasedSemen(), 4));
    app(SyncOperationalPostings::class)();
    $boss = owner();

    $yield = app(GetSlaughterYield::class)(now()->startOfMonth(), now())['totals'];
    $profit = app(GetProfitability::class)(now()->startOfMonth(), now())['totals'];
    $month = app(GetTargetVsActual::class)((int) now()->year, (int) now()->month, $boss, now());
    $snapshot = collect(app(GetOwnerSnapshot::class)($boss)['today'])->keyBy('key');
    $report = app(GetMonthlyReport::class)((int) now()->year, (int) now()->month, $boss);

    expect($month['slaughter.dressing_percent']['value'])->toBe($yield['dressing_percent'])->and($month['finance.revenue_minor']['value'])->toBe($profit['revenue_minor'])
        ->and($report['kpis']['finance.revenue_minor']['value'])->toBe($month['finance.revenue_minor']['value'])->and($report['profitability']['totals']['revenue_minor'])->toBe($profit['revenue_minor'])
        ->and($snapshot['sales.invoiced_minor']['value'])->toBe($pay->total_minor);
});

it('shows each person only the indicators of the modules they may view', function () {
    $store = userWithRole('Store Officer');                                 // sees inventory, semen, sales (view) but not finance

    $keys = array_keys(app(GetKpis::class)(now()->subDay(), now(), $store));

    expect($keys)->toContain('sales.invoiced_minor')->not->toContain('finance.revenue_minor')->and(app(GetKpis::class)(now()->subDay(), now(), farmWorker()))->not->toHaveKey('sales.invoiced_minor');
});

it('takes a month\'s target over the year\'s, and the year\'s over the farm setting', function () {
    $year = (int) now()->year;
    $boss = owner();
    $dressing = fn () => app(GetTargetVsActual::class)($year, (int) now()->month, $boss, now())['slaughter.dressing_percent'];

    expect($dressing()['target'])->toBe('75');                               // the farm setting, until management sets one

    app(SetKpiTarget::class)('slaughter.dressing_percent', $year, null, '77');
    expect($dressing()['target'])->toBe('77');

    app(SetKpiTarget::class)('slaughter.dressing_percent', $year, (int) now()->month, '78.5');
    expect($dressing()['target'])->toBe('78.5');

    app(SetKpiTarget::class)('slaughter.dressing_percent', $year, (int) now()->month === 1 ? 2 : 1, '99');   // another month: not used
    expect($dressing()['target'])->toBe('78.5');
});

it('says whether a target was met by the direction that is good for the indicator', function () {
    $year = (int) now()->year;
    $month = (int) now()->month;
    $boss = owner();
    slaughterPig();                                                           // dressing 76.00
    scenario();                                                               // batch mortality 2.00%

    $set = fn (string $key, string $value) => app(SetKpiTarget::class)($key, $year, null, $value);
    $set('slaughter.dressing_percent', '75');
    $set('production.mortality_percent', '1');
    $set('production.active_batches', '5');                                   // no direction: never met or missed

    $rows = app(GetTargetVsActual::class)($year, $month, $boss, now());

    expect($rows['slaughter.dressing_percent'])->toMatchArray(['status' => 'met', 'attainment_percent' => '101.3'])
        ->and($rows['production.mortality_percent']['status'])->toBe('missed')      // 2.00 is above the 1 allowed
        ->and($rows['production.active_batches']['status'])->toBe('none')
        ->and($rows['finance.cash_minor']['status'])->toBe('none');                  // no target set

    app(SetKpiTarget::class)('production.mortality_percent', $year, null, '2', null, KpiTarget::firstWhere('kpi_key', 'production.mortality_percent'));   // equal to the limit is still met

    expect(app(GetTargetVsActual::class)($year, $month, $boss, now())['production.mortality_percent']['status'])->toBe('met');
});

it('keeps one target per indicator and period, including the whole-year row', function () {
    $set = fn (...$a) => app(SetKpiTarget::class)(...$a);
    $first = $set('finance.cash_minor', 2026, null, '100');

    expect(fn () => $set('finance.cash_minor', 2026, null, '200'))->toThrow(DomainException::class, 'already a target')
        ->and(fn () => $set('nope.nothing', 2026, null, '1'))->toThrow(DomainException::class, 'indicators')
        ->and(fn () => $set('finance.cash_minor', 2026, 13, '1'))->toThrow(DomainException::class, 'month from 1 to 12')
        ->and(fn () => $set('finance.cash_minor', 1999, null, '1'))->toThrow(DomainException::class, 'month from 1 to 12')
        ->and(fn () => $set('finance.cash_minor', 2026, 3, '-5'))->toThrow(DomainException::class, 'number of zero or more')
        ->and(fn () => $set('finance.cash_minor', 2026, 3, 'abc'))->toThrow(DomainException::class, 'number of zero or more');

    // Editing the row keeps it one row, and may move it to a free period, but not onto a taken one.
    $moved = $set('finance.cash_minor', 2026, 4, '150', 'Q2', $first);
    $set('finance.cash_minor', 2026, null, '90');

    expect($moved->id)->toBe($first->id)->and(KpiTarget::count())->toBe(2)->and(fn () => $set('finance.cash_minor', 2026, null, '1', null, $moved))->toThrow(DomainException::class, 'already a target');
});

it('gives the owner a snapshot of the day limited to what they may see', function () {
    $invoice = dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    newTask(['dueOn' => now()->subDay()]);
    $boss = owner();

    $s = app(GetOwnerSnapshot::class)($boss);
    $today = collect($s['today'])->keyBy('key');

    expect($today['sales.invoiced_minor']['value'])->toBe($invoice->total_minor)->and($s['attention']['overdue_tasks'])->toBe(1)->and($s['attention']['critical_alerts'])->toBeInt()
        ->and(collect($s['now'])->pluck('key')->all())->toContain('herd.active_animals', 'finance.cash_minor');

    $worker = app(GetOwnerSnapshot::class)(farmWorker());

    expect(collect($worker['today'])->pluck('key')->all())->not->toContain('sales.invoiced_minor')->and(collect($worker['now'])->pluck('key')->all())->not->toContain('finance.cash_minor');
});

it('generates the monthly management report with the sections the reader may see', function () {
    $invoice = dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    app(SyncOperationalPostings::class)();
    $boss = owner();

    $r = app(GetMonthlyReport::class)((int) now()->year, (int) now()->month, $boss);

    expect($r['label'])->toBe(now()->format('F Y'))->and($r['profitability']['totals']['revenue_minor'])->toBe($invoice->total_minor)->and($r['cash_flow'])->not->toBeNull()
        ->and($r['ageing']['receivables']['total_minor'])->toBe($invoice->total_minor)->and($r['tasks'])->toMatchArray(['created' => 0, 'completed' => 0, 'overdue' => 0])
        ->and($r['alerts'])->toHaveKeys(['danger', 'warning', 'info']);

    $store = app(GetMonthlyReport::class)((int) now()->year, (int) now()->month, userWithRole('Store Officer'));

    expect($store['profitability'])->toBeNull()->and($store['cash_flow'])->toBeNull()->and($store['budget'])->toBeNull();
});

it('exports a report as CSV, Excel and PDF files', function () {
    $doc = (new ReportDocument('Test report', 'October 2026'))->with('Figures', ['Name', 'Amount'], [['Sales', 'NGN 1,500.00'], ['Loss', '-NGN 20.00'], ['Note, with comma', 'ok']]);
    $export = app(ExportReport::class);

    $csv = $export->csv($doc);
    $xlsx = $export->xlsx($doc);
    $pdf = $export->pdf($doc);

    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('"Test report","October 2026"')->toContain('Figures')->toContain('Name,Amount')->toContain('Sales,"NGN 1,500.00"')->toContain('Loss,"-NGN 20.00"')->toContain('"Note, with comma",ok')
        ->and(substr($xlsx, 0, 2))->toBe('PK')->and(substr($pdf, 0, 5))->toBe('%PDF-')->and($doc->filename('pdf'))->toBe('test-report-october-2026.pdf');
});

it('keeps spreadsheet formulas typed into the data as text', function () {
    $doc = (new ReportDocument('Test', 'x'))->with('Names', ['Name'], [['=HYPERLINK("http://evil")'], ['+1+1'], ['@SUM(A1)'], ['-cmd|calc'], ['-NGN 20.00'], ['-5']]);

    $csv = app(ExportReport::class)->csv($doc);

    expect($csv)->toContain("'=HYPERLINK")->toContain("'+1+1")->toContain("'@SUM")->toContain("'-cmd|calc")->and($csv)->not->toContain("'-NGN")->not->toContain("'-5");
});

it('builds the monthly report document with every figure the screen shows', function () {
    dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    app(SyncOperationalPostings::class)();

    $doc = app(ReportDocuments::class)->monthly(app(GetMonthlyReport::class)((int) now()->year, (int) now()->month, owner()));
    $titles = collect($doc->sections)->pluck('title')->all();

    expect($doc->title)->toBe('Monthly management report')->and($titles)->toContain('Finance', 'Sales', 'Business units', 'Summary', 'Alerts standing now', 'Tasks')
        ->and(collect($doc->sections)->firstWhere('title', 'Business units')['rows'][0][1])->toContain('150,000.00');
});
