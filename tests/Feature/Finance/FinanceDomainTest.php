<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Finance\Actions\DecideJournal;
use App\Domain\Finance\Actions\GetBudgetVsActual;
use App\Domain\Finance\Actions\GetCashFlow;
use App\Domain\Finance\Actions\GetProfitability;
use App\Domain\Finance\Actions\GetReceivablesPayables;
use App\Domain\Finance\Actions\GetTrialBalance;
use App\Domain\Finance\Actions\GetUnitCosts;
use App\Domain\Finance\Actions\PostJournal;
use App\Domain\Finance\Actions\RecordCashTransaction;
use App\Domain\Finance\Actions\RecordExpense;
use App\Domain\Finance\Actions\ReverseJournal;
use App\Domain\Finance\Actions\SaveBudget;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Finance\Actions\VoidFinanceRecord;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Finance\Models\ExpenseRecord;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Procurement\Actions\RecordSupplierInvoice;
use App\Domain\Procurement\Actions\RecordSupplierPayment;
use App\Domain\Sales\Actions\VoidCustomerPayment;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CashDirection;
use App\Enums\JournalStatus;
use App\Enums\PaymentMethod;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

it('seeds the ten cost centres and the system accounts, and seeding again changes nothing', function () {
    $this->seed(FinanceSeeder::class);

    expect(CostCentre::count())->toBe(10)
        ->and(CostCentre::pluck('name')->all())->toContain('Breeding', 'Piglet production', 'Grower', 'Finisher', 'Semen production', 'Feed mill', 'Slaughter', 'Meat processing', 'Sales and distribution', 'Administration')
        ->and(Account::whereNotNull('system_key')->count())->toBe(8);
});

it('posts a balanced entry and refuses one that does not balance, is one-sided or has too few lines', function () {
    $entry = finPost('cash', 'sales_pigs', 500000);

    expect($entry->status)->toBe(JournalStatus::Posted)->and($entry->total_minor)->toBe(500000)->and($entry->number)->toBe('JE-000001')->and($entry->lines)->toHaveCount(2);

    $post = fn (array $lines) => app(PostJournal::class)(now(), 'Bad', $lines);
    $cash = finId('cash');
    $sales = finId('sales_pigs');

    expect(fn () => $post([['account_id' => $cash, 'debit_minor' => 100], ['account_id' => $sales, 'credit_minor' => 90]]))->toThrow(DomainException::class, 'does not balance')
        ->and(fn () => $post([['account_id' => $cash, 'debit_minor' => 100, 'credit_minor' => 100], ['account_id' => $sales, 'credit_minor' => 0]]))->toThrow(DomainException::class, 'either a debit or a credit')
        ->and(fn () => $post([['account_id' => $cash, 'debit_minor' => 100]]))->toThrow(DomainException::class, 'at least two lines')
        ->and(fn () => app(PostJournal::class)(now()->addDays(2), 'Future', [['account_id' => $cash, 'debit_minor' => 1], ['account_id' => $sales, 'credit_minor' => 1]]))->toThrow(DomainException::class, 'future');
});

it('keeps posted entries immutable', function () {
    $entry = finPost('cash', 'sales_pigs', 100);

    expect(fn () => $entry->update(['description' => 'Changed']))->toThrow(LogicException::class)
        ->and(fn () => $entry->lines->first()->update(['debit_minor' => 5]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('refuses to post on or before the date the books were closed through', function () {
    app(ResolveSettings::class)->set('finance.books_closed_through', now()->subDays(5)->toDateString());

    expect(fn () => finPost('cash', 'sales_pigs', 100, now()->subDays(5)->toDateString()))->toThrow(DomainException::class, 'books are closed')
        ->and(finPost('cash', 'sales_pigs', 100, now()->subDays(4)->toDateString())->exists)->toBeTrue();
});

it('is idempotent on the source key', function () {
    $lines = [['account_id' => finId('cash'), 'debit_minor' => 100], ['account_id' => finId('sales_pigs'), 'credit_minor' => 100]];
    $a = app(PostJournal::class)(now(), 'Once', $lines, JournalStatus::Posted, 'doc:1');
    $b = app(PostJournal::class)(now(), 'Once', $lines, JournalStatus::Posted, 'doc:1');

    expect($b->id)->toBe($a->id)->and(JournalEntry::count())->toBe(1);
});

it('keeps a manual entry out of the books until somebody else approves it', function () {
    $clerk = userWithRole('Accountant');
    $manager = owner();
    $entry = app(PostJournal::class)(now(), 'Owner capital', [['account_id' => finId('bank'), 'debit_minor' => 9000000], ['account_id' => Account::firstWhere('code', '3000')->id, 'credit_minor' => 9000000]], JournalStatus::Pending, null, $clerk);

    expect(finBalance('bank'))->toBe(0)
        ->and(fn () => app(DecideJournal::class)->approve($entry, $clerk))->toThrow(DomainException::class, 'someone other than')
        ->and(fn () => app(DecideJournal::class)->approve($entry, userWithRole('Sales Officer')))->toThrow(DomainException::class, 'not authorised');

    app(DecideJournal::class)->approve($entry, $manager);

    expect(finBalance('bank'))->toBe(9000000)->and($entry->refresh()->status)->toBe(JournalStatus::Posted)->and($entry->decided_by)->toBe($manager->id)
        ->and(fn () => app(DecideJournal::class)->approve($entry, $manager))->toThrow(DomainException::class, 'already been decided');
});

it('rejects a manual entry only with a reason, and a rejected entry never counts', function () {
    $entry = finPost('bank', 'sales_pigs', 700, null, JournalStatus::Pending);

    expect(fn () => app(DecideJournal::class)->reject($entry, owner(), ' '))->toThrow(DomainException::class, 'reason');

    app(DecideJournal::class)->reject($entry, owner(), 'Wrong account');

    expect($entry->refresh()->status)->toBe(JournalStatus::Rejected)->and(finBalance('bank'))->toBe(0);
});

it('reverses a posted entry once, with swapped sides, and a reversal cannot itself be reversed', function () {
    $entry = finPost('cash', 'sales_pigs', 250000);
    $reversal = app(ReverseJournal::class)($entry, 'Entered twice');

    expect($reversal->reverses_id)->toBe($entry->id)->and($reversal->lines->firstWhere('account_id', finId('cash'))->credit_minor)->toBe(250000)
        ->and(finBalance('cash'))->toBe(0)->and(finBalance('sales_pigs'))->toBe(0)
        ->and(fn () => app(ReverseJournal::class)($entry, 'Again'))->toThrow(DomainException::class, 'already been reversed')
        ->and(fn () => app(ReverseJournal::class)($reversal, 'Undo'))->toThrow(DomainException::class, 'cannot itself be reversed')
        ->and(fn () => app(ReverseJournal::class)($entry, ' '))->toThrow(DomainException::class, 'reason');
});

it('records an expense paid in cash or owed, and refuses a non-expense account', function () {
    $labour = Account::firstWhere('code', '5300');
    $paid = app(RecordExpense::class)(now(), $labour, finCentre('ADM'), 300000, finId('cash'), 'Field crew');
    $owed = app(RecordExpense::class)(now(), $labour, finCentre('GRW'), 120000);

    expect($paid->number)->toBe('EX-000001')->and(finBalance('cash'))->toBe(-300000)->and(finBalance('payables'))->toBe(120000)
        ->and(finBalance('purchases'))->toBe(0)->and($owed->paid_from_account_id)->toBeNull()
        ->and(fn () => app(RecordExpense::class)(now(), finId('cash'), finCentre('ADM'), 100))->toThrow(DomainException::class, 'not an expense account')
        ->and(fn () => app(RecordExpense::class)(now(), $labour, finCentre('ADM'), 0))->toThrow(DomainException::class, 'more than zero')
        ->and(fn () => app(RecordExpense::class)(now(), $labour, finCentre('ADM'), 100, finId('payables')))->toThrow(DomainException::class, 'cash or bank');
});

it('voids an expense by reversing its entry and keeps the record', function () {
    finOverhead(300000);
    $expense = ExpenseRecord::sole();

    expect(fn () => app(VoidFinanceRecord::class)($expense, ''))->toThrow(DomainException::class, 'reason');

    app(VoidFinanceRecord::class)($expense, 'Duplicate');

    expect($expense->refresh()->isVoided())->toBeTrue()->and(finBalance('cash'))->toBe(0)->and(JournalEntry::count())->toBe(2)
        ->and(fn () => app(VoidFinanceRecord::class)($expense, 'Again'))->toThrow(DomainException::class, 'already voided')
        ->and(fn () => $expense->delete())->toThrow(LogicException::class);
});

it('calculates cash flow from cash transactions, expenses and receipts', function () {
    // Opening: 1,000,000 in the bank on a day before the period.
    finPost('bank', 'sales_pigs', 1000000, now()->subDays(40)->toDateString());

    app(RecordCashTransaction::class)(now()->subDays(5), CashDirection::In, finId('bank'), Account::firstWhere('code', '3000'), 4000000);   // owner's capital, 4,000,000 in
    finOverhead(300000);                                                                                                                       // 300,000 out in cash
    app(RecordCashTransaction::class)(now(), CashDirection::Out, finId('bank'), Account::firstWhere('code', '5400'), 250000, finCentre('ADM'));  // fuel, 250,000 out

    $flow = app(GetCashFlow::class)(now()->subDays(10), now());

    expect($flow['opening_minor'])->toBe(1000000)->and($flow['in_minor'])->toBe(4000000)->and($flow['out_minor'])->toBe(550000)->and($flow['net_minor'])->toBe(3450000)
        ->and($flow['closing_minor'])->toBe(4450000)->and($flow['inflows'])->toBe(['Other' => 4000000])->and($flow['outflows'])->toBe(['Operating expenses' => 550000]);
});

it('does not let a cash transaction touch another cash or bank account, or a zero amount', function () {
    $record = fn (int $counter, int $minor = 100) => app(RecordCashTransaction::class)(now(), CashDirection::In, finId('bank'), $counter, $minor);

    expect(fn () => $record(finId('cash')))->toThrow(DomainException::class, 'cannot be a cash or bank')
        ->and(fn () => $record(finId('sales_pigs'), 0))->toThrow(DomainException::class, 'more than zero')
        ->and(fn () => app(RecordCashTransaction::class)(now(), CashDirection::In, finId('sales_pigs'), finId('purchases'), 100))->toThrow(DomainException::class, 'cash or bank account');
});

it('carries a sale and its receipt into the books, once, and reflects a voided receipt', function () {
    $invoice = dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));     // 10 doses x 15,000.00 = 15,000,000 minor
    $customer = $invoice->customer;
    $payment = pay($customer, 6000000);

    $first = app(SyncOperationalPostings::class)();
    $again = app(SyncOperationalPostings::class)();

    expect($first['posted'])->toBe(2)->and($first['skipped'])->toBe([])->and($again['posted'])->toBe(0)
        ->and(finBalance('sales_semen'))->toBe(15000000)->and(finBalance('receivables'))->toBe(9000000)->and(finBalance('bank'))->toBe(6000000)
        ->and(JournalEntry::firstWhere('source_key', "invoice:{$invoice->id}")->lines->firstWhere('account_id', finId('sales_semen'))->cost_centre_id)->toBe(finCentre('SEM'));

    app(VoidCustomerPayment::class)($payment, 'Cheque bounced');
    $voided = app(SyncOperationalPostings::class)();

    expect($voided['reversed'])->toBe(1)->and(finBalance('bank'))->toBe(0)->and(finBalance('receivables'))->toBe(15000000)
        ->and(app(SyncOperationalPostings::class)()['reversed'])->toBe(0)
        ->and(app(GetTrialBalance::class)()['balanced'])->toBeTrue();
});

it('carries supplier invoices and payments into the books', function () {
    $order = purchaseOrder();
    receiveGoods($order, ['100']);
    $invoice = app(RecordSupplierInvoice::class)($order, 'INV-9', now(), 3500000);
    app(RecordSupplierPayment::class)($invoice, 1000000, now(), PaymentMethod::BankTransfer);

    app(SyncOperationalPostings::class)();

    expect(finBalance('purchases'))->toBe(3500000)->and(finBalance('payables'))->toBe(2500000)->and(finBalance('bank'))->toBe(-1000000);
});

it('skips a document dated in a closed period and says so', function () {
    $invoice = dispatched(semenOrder(creditCustomer(), releasedSemen(), 2), 3);
    app(ResolveSettings::class)->set('finance.books_closed_through', now()->subDay()->toDateString());

    $result = app(SyncOperationalPostings::class)();

    expect($result['posted'])->toBe(0)->and($result['skipped'])->toHaveCount(1)->and($result['skipped'][0])->toContain($invoice->number, 'books are closed');
});

it('ages what customers and suppliers are owed by days past due', function () {
    $customer = creditCustomer(1000000000, 30);
    dispatched(semenOrder($customer, releasedSemen(), 2), 40);     // 3,000,000, due 10 days ago
    pay($customer, 1000000);                                      // 1,000,000 on account (settles the oldest invoice)
    $order = purchaseOrder();
    receiveGoods($order, ['100']);
    app(RecordSupplierInvoice::class)($order, 'INV-1', now()->subDays(80), 3500000);   // due 50 days ago

    $report = app(GetReceivablesPayables::class)();

    expect($report['receivables']['total_minor'])->toBe(2000000)->and($report['receivables']['buckets']['1_30'])->toBe(2000000)->and($report['receivables']['parties'][0]['name'])->toBe('Green Acres Farm')
        ->and($report['payables']['total_minor'])->toBe(3500000)->and($report['payables']['buckets']['31_60'])->toBe(3500000)->and($report['payables']['buckets']['current'])->toBe(0);
});

it('compares an approved budget with posted actuals and marks the variance favourable or not', function () {
    $author = userWithRole('Accountant');
    $month = (int) now()->month;
    $labour = Account::firstWhere('code', '5300')->id;
    $budget = app(SaveBudget::class)('Farm plan', (int) now()->year, [
        ['account_id' => $labour, 'cost_centre_id' => finCentre('ADM'), 'month' => $month, 'amount_minor' => 500000],
        ['account_id' => finId('sales_semen'), 'cost_centre_id' => finCentre('SEM'), 'month' => $month, 'amount_minor' => 10000000],
        ['account_id' => $labour, 'cost_centre_id' => finCentre('ADM'), 'month' => 12, 'amount_minor' => 999999],   // a later month: not yet due
    ], null, null, $author);

    expect(fn () => app(SaveBudget::class)->approve($budget, $author))->toThrow(DomainException::class, 'someone other than');

    app(SaveBudget::class)->approve($budget, owner());

    expect(fn () => app(SaveBudget::class)('Changed', (int) now()->year, [['account_id' => $labour, 'month' => 1, 'amount_minor' => 1]], null, $budget))->toThrow(DomainException::class, 'approved budget');

    finOverhead(300000);
    finOverhead(350000);
    dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    app(SyncOperationalPostings::class)();

    $report = app(GetBudgetVsActual::class)($budget, $month);
    $rows = collect($report['rows'])->keyBy(fn ($r) => $r['account']->code);

    expect($report['rows'])->toHaveCount(2)
        ->and($rows['5300'])->toMatchArray(['budget_minor' => 500000, 'actual_minor' => 650000, 'variance_minor' => 150000, 'variance_percent' => '30.0', 'favourable' => false])
        ->and($rows['4010'])->toMatchArray(['budget_minor' => 10000000, 'actual_minor' => 15000000, 'variance_minor' => 5000000, 'variance_percent' => '50.0', 'favourable' => true])
        ->and($report['totals'])->toBe(['revenue_budget_minor' => 10000000, 'revenue_actual_minor' => 15000000, 'expense_budget_minor' => 500000, 'expense_actual_minor' => 650000]);
});

it('shows spending nobody budgeted against a plan of zero', function () {
    $budget = app(SaveBudget::class)('Plan', (int) now()->year, [['account_id' => finId('sales_pigs'), 'month' => (int) now()->month, 'amount_minor' => 100]]);
    finOverhead(40000);   // labour: not in the plan

    $report = app(GetBudgetVsActual::class)($budget);
    $labour = collect($report['rows'])->first(fn ($r) => $r['account']->code === '5300');

    expect($labour)->toMatchArray(['budget_minor' => 0, 'actual_minor' => 40000, 'variance_percent' => null, 'favourable' => false])
        ->and($report['totals']['expense_actual_minor'])->toBe(40000);
});

it('validates budget lines', function () {
    $save = fn (array $line, string $name = 'Plan', int $year = 2026) => app(SaveBudget::class)($name, $year, [$line]);

    expect(fn () => $save(['account_id' => finId('cash'), 'month' => 1, 'amount_minor' => 1]))->toThrow(DomainException::class, 'revenue or expense')
        ->and(fn () => $save(['account_id' => finId('sales_pigs'), 'month' => 13, 'amount_minor' => 1]))->toThrow(DomainException::class, 'month from 1 to 12')
        ->and(fn () => $save(['account_id' => finId('sales_pigs'), 'month' => 1, 'amount_minor' => -5]))->toThrow(DomainException::class, 'month from 1 to 12')
        ->and(fn () => $save(['account_id' => finId('sales_pigs'), 'month' => 1, 'amount_minor' => 1], ' '))->toThrow(DomainException::class, 'name and a year');
});

it('shows profitability by business unit from sales, direct costs and overheads', function () {
    // Revenue: 10 semen doses sold at 15,000.00 = 15,000,000 (semen production).
    $batch = releasedSemen();
    dispatched(semenOrder(creditCustomer(), $batch, 10));
    app(SyncOperationalPostings::class)();
    // Direct costs: the semen batch at 2,000.00 a dose, and the grower batch's feed and labour (183,750,000 + 2,000,000).
    app(ResolveSettings::class)->set('semen.cost_per_dose_minor', 200000);
    scenario();
    // Overhead: labour paid in cash against administration.
    finOverhead(300000);

    $doses = $batch->refresh()->doses_produced;
    $report = app(GetProfitability::class)(now()->subDays(40), now());
    $rows = collect($report['rows'])->keyBy(fn ($r) => $r['centre']->code);
    $semenCost = $doses * 200000;

    expect($rows['SEM']['revenue_minor'])->toBe(15000000)->and($rows['SEM']['direct_cost_minor'])->toBe($semenCost)->and($rows['SEM']['gross_margin_minor'])->toBe(15000000 - $semenCost)
        ->and($rows['GRW']['direct_cost_minor'])->toBe(185750000)->and($rows['GRW']['revenue_minor'])->toBe(0)
        ->and($rows['ADM']['overhead_minor'])->toBe(300000)->and($rows['ADM']['net_margin_minor'])->toBe(-300000)
        ->and($report['totals']['revenue_minor'])->toBe(15000000)->and($report['totals']['direct_cost_minor'])->toBe($semenCost + 185750000)
        ->and($report['totals']['net_margin_minor'])->toBe(15000000 - $semenCost - 185750000 - 300000);
});

it('leaves supplier purchases out of overheads, so what they bought is not charged twice', function () {
    $order = purchaseOrder();
    receiveGoods($order, ['100']);
    app(RecordSupplierInvoice::class)($order, 'INV-2', now(), 3500000);
    app(SyncOperationalPostings::class)();

    expect(finBalance('purchases'))->toBe(3500000)->and(app(GetProfitability::class)(now()->subDay(), now())['totals']['overhead_minor'])->toBe(0);
});

it('works out what a pig, a kg of live weight, feed, semen and meat cost', function () {
    scenario();                       // 235,750,000 over 98 surviving pigs at 50 kg
    app(ResolveSettings::class)->set('semen.cost_per_dose_minor', 100000);
    $semen = releasedSemen();
    $carcass = slaughterPig();
    $meat = makeMeat($carcass);

    $costs = app(GetUnitCosts::class)(now()->subDays(40), now());

    expect($costs['pigs'])->toHaveCount(1)
        ->and($costs['pigs'][0])->toMatchArray(['pigs' => 98, 'total_cost_minor' => 235750000, 'cost_per_pig_minor' => 2405612, 'live_weight_kg' => '4900.00', 'cost_per_kg_live_minor' => 48112])
        ->and($costs['semen'][0])->toMatchArray(['batch' => $semen->number, 'cost_per_dose_minor' => 100000, 'total_cost_minor' => $semen->doses_produced * 100000])
        ->and(collect($costs['meat'])->sum('total_cost_minor'))->toBe($meat->total_cost_minor);
});

it('costs the feed made in a period per kg', function () {
    $run = completedFeedRun(millFixture());
    $costs = app(GetUnitCosts::class)(now()->subDays(2), now());

    expect($costs['feed'])->toHaveCount(1)->and($costs['feed'][0]['cost_per_kg_minor'])->toBe($run->cost_per_kg_minor)->and($costs['feed'][0]['total_cost_minor'])->toBe($run->total_cost_minor);
});

it('keeps the books balanced after any mix of postings, reversals and voids', function () {
    finPost('cash', 'sales_pigs', 1234567);
    app(ReverseJournal::class)(finPost('bank', 'sales_meat', 88000), 'Mistake');
    finOverhead(4000);
    app(VoidFinanceRecord::class)(ExpenseRecord::sole(), 'Oops');

    $tb = app(GetTrialBalance::class)();

    expect($tb['balanced'])->toBeTrue()->and($tb['debit_minor'])->toBe($tb['credit_minor']);
});
