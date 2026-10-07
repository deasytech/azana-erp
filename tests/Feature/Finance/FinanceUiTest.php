<?php

use App\Domain\Finance\Actions\SaveBudget;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Models\CashTransaction;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Finance\Models\ExpenseRecord;
use App\Domain\Finance\Models\JournalEntry;
use App\Enums\BudgetStatus;
use App\Enums\JournalStatus;
use App\Filament\Pages\BudgetVsActualReport;
use App\Filament\Pages\CashFlowReport;
use App\Filament\Pages\ProfitabilityReport;
use App\Filament\Pages\ReceivablesPayables;
use App\Filament\Pages\TrialBalanceReport;
use App\Filament\Pages\UnitCostsReport;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\CashTransactions\CashTransactionResource;
use App\Filament\Resources\CashTransactions\Pages\CreateCashTransaction;
use App\Filament\Resources\CashTransactions\Pages\ListCashTransactions;
use App\Filament\Resources\CostCentres\CostCentreResource;
use App\Filament\Resources\ExpenseRecords\ExpenseRecordResource;
use App\Filament\Resources\ExpenseRecords\Pages\CreateExpenseRecord;
use App\Filament\Resources\ExpenseRecords\Pages\ListExpenseRecords;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Resources\JournalEntries\Pages\CreateJournalEntry;
use App\Filament\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Filament\Support\MoneyColumn;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('renders every finance page for the owner', function () {
    $this->actingAs(owner());
    $entry = finPost('cash', 'sales_pigs', 100000);
    finOverhead(5000);

    foreach ([
        JournalEntryResource::getUrl('index'), JournalEntryResource::getUrl('create'), JournalEntryResource::getUrl('view', ['record' => $entry]),
        ExpenseRecordResource::getUrl('index'), ExpenseRecordResource::getUrl('create'), CashTransactionResource::getUrl('index'), CashTransactionResource::getUrl('create'),
        BudgetResource::getUrl('index'), BudgetResource::getUrl('create'), AccountResource::getUrl('index'), AccountResource::getUrl('create'), CostCentreResource::getUrl('index'),
        ProfitabilityReport::getUrl(), CashFlowReport::getUrl(), UnitCostsReport::getUrl(), ReceivablesPayables::getUrl(), TrialBalanceReport::getUrl(), BudgetVsActualReport::getUrl(),
    ] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('keeps finance away from people without its permission and gives the accountant the lot', function () {
    $this->actingAs(farmWorker());
    $this->get(JournalEntryResource::getUrl('index'))->assertForbidden();
    $this->get(ProfitabilityReport::getUrl())->assertForbidden();
    $this->get(BudgetResource::getUrl('index'))->assertForbidden();

    $this->actingAs(userWithRole('Accountant'));
    $this->get(JournalEntryResource::getUrl('index'))->assertSuccessful();
    $this->get(CashFlowReport::getUrl())->assertSuccessful();
});

it('enters a manual journal that waits, then another user approves it from the list', function () {
    $clerk = userWithRole('Accountant');
    $this->actingAs($clerk);
    $capital = Account::firstWhere('code', '3000');

    Livewire::test(CreateJournalEntry::class)
        ->fillForm(['entry_date' => now()->toDateString(), 'description' => 'Owner capital', 'lines' => [
            ['account_id' => finId('bank'), 'debit_minor' => '5000.00'], ['account_id' => $capital->id, 'credit_minor' => '5000.00'],
        ]])->call('create')->assertHasNoFormErrors();

    $entry = JournalEntry::sole();
    expect($entry->status)->toBe(JournalStatus::Pending)->and($entry->total_minor)->toBe(500000);

    // The person who entered it cannot approve it.
    Livewire::test(ListJournalEntries::class)->callAction(TestAction::make('approve')->table($entry))->assertNotified('Not saved');
    expect($entry->fresh()->status)->toBe(JournalStatus::Pending);

    $this->actingAs(owner());
    Livewire::test(ListJournalEntries::class)->assertCanSeeTableRecords([$entry])->callAction(TestAction::make('approve')->table($entry))->assertNotified('Entry posted');
    expect($entry->fresh()->status)->toBe(JournalStatus::Posted);

    Livewire::test(ListJournalEntries::class)->callAction(TestAction::make('reverse')->table($entry), ['reason' => 'Wrong year'])->assertNotified('Entry reversed');
    expect(JournalEntry::count())->toBe(2)->and(finBalance('bank'))->toBe(0);
});

it('tells the user why an unbalanced manual journal was refused', function () {
    $this->actingAs(userWithRole('Accountant'));

    Livewire::test(CreateJournalEntry::class)
        ->fillForm(['entry_date' => now()->toDateString(), 'description' => 'Bad', 'lines' => [
            ['account_id' => finId('bank'), 'debit_minor' => '50.00'], ['account_id' => finId('sales_pigs'), 'credit_minor' => '40.00'],
        ]])->call('create')->assertNotified('Not saved');

    expect(JournalEntry::count())->toBe(0);
});

it('posts the sales and receipts waiting to be booked from the journal page', function () {
    $this->actingAs(owner());
    dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));

    Livewire::test(ListJournalEntries::class)->callAction('sync')->assertNotified('1 posted, 0 reversed');
    expect(finBalance('receivables'))->toBe(15000000);
});

it('records an expense, shows it, and voids it with a reason', function () {
    $this->actingAs(userWithRole('Accountant'));

    Livewire::test(CreateExpenseRecord::class)
        ->fillForm(['expense_date' => now()->toDateString(), 'account_id' => Account::firstWhere('code', '5300')->id, 'cost_centre_id' => finCentre('GRW'), 'amount_minor' => '1200.50', 'paid_from_account_id' => finId('cash'), 'payee' => 'Field crew'])
        ->call('create')->assertHasNoFormErrors();

    $expense = ExpenseRecord::sole();
    expect($expense->amount_minor)->toBe(120050)->and(finBalance('cash'))->toBe(-120050);

    Livewire::test(ListExpenseRecords::class)->assertCanSeeTableRecords([$expense])->callAction(TestAction::make('void')->table($expense), ['reason' => 'Duplicate'])->assertNotified('Voided');
    expect($expense->fresh()->isVoided())->toBeTrue()->and(finBalance('cash'))->toBe(0);

    Livewire::test(ListExpenseRecords::class)->assertActionHidden(TestAction::make('void')->table($expense));
});

it('offers only expense accounts when recording an expense', function () {
    $this->actingAs(userWithRole('Accountant'));

    Livewire::test(CreateExpenseRecord::class)
        ->fillForm(['expense_date' => now()->toDateString(), 'account_id' => finId('cash'), 'cost_centre_id' => finCentre('GRW'), 'amount_minor' => '10.00'])
        ->call('create')->assertHasFormErrors(['account_id']);
});

it('records money in and out, and refuses a bank-to-cash pairing', function () {
    $this->actingAs(userWithRole('Accountant'));
    $capital = Account::firstWhere('code', '3000')->id;

    Livewire::test(CreateCashTransaction::class)
        ->fillForm(['transaction_date' => now()->toDateString(), 'direction' => 'in', 'cash_account_id' => finId('bank'), 'counter_account_id' => $capital, 'amount_minor' => '8000.00'])
        ->call('create')->assertHasNoFormErrors();

    expect(finBalance('bank'))->toBe(800000);

    $transaction = CashTransaction::sole();
    Livewire::test(ListCashTransactions::class)->assertCanSeeTableRecords([$transaction])->callAction(TestAction::make('void')->table($transaction), ['reason' => 'Wrong bank'])->assertNotified('Voided');
    expect(finBalance('bank'))->toBe(0);
});

it('makes a budget, edits it while a draft, and locks it once approved', function () {
    $clerk = userWithRole('Accountant');
    $this->actingAs($clerk);
    $labour = Account::firstWhere('code', '5300')->id;

    Livewire::test(CreateBudget::class)
        ->fillForm(['name' => 'Farm plan', 'fiscal_year' => 2026, 'lines' => [['account_id' => $labour, 'cost_centre_id' => finCentre('ADM'), 'month' => 3, 'amount_minor' => '2500.00']]])
        ->call('create')->assertHasNoFormErrors();

    $budget = Budget::sole();
    expect($budget->lines)->toHaveCount(1)->and($budget->lines->first()->amount_minor)->toBe(250000)->and($budget->status)->toBe(BudgetStatus::Draft);

    Livewire::test(EditBudget::class, ['record' => $budget->getRouteKey()])->assertFormSet(['name' => 'Farm plan'])
        ->fillForm(['name' => 'Farm plan v2', 'lines' => [['account_id' => $labour, 'month' => 4, 'amount_minor' => '3000.00']]])->call('save')->assertHasNoFormErrors();
    expect($budget->fresh()->name)->toBe('Farm plan v2')->and($budget->lines()->count())->toBe(1)->and($budget->lines()->first()->month)->toBe(4);

    Livewire::test(ListBudgets::class)->callAction(TestAction::make('approve')->table($budget))->assertNotified('Not saved');   // the author cannot approve
    $this->actingAs(owner());
    Livewire::test(ListBudgets::class)->callAction(TestAction::make('approve')->table($budget))->assertNotified('Budget approved');
    Livewire::test(ListBudgets::class)->assertActionHidden(TestAction::make('approve')->table($budget));
    expect($budget->fresh()->isApproved())->toBeTrue();
});

it('shows the budget report, and says so when there is no budget', function () {
    $this->actingAs(owner());

    Livewire::test(BudgetVsActualReport::class)->assertSee('There is no budget yet');

    $budget = app(SaveBudget::class)('Plan', (int) now()->year, [['account_id' => Account::firstWhere('code', '5300')->id, 'cost_centre_id' => finCentre('ADM'), 'month' => (int) now()->month, 'amount_minor' => 500000]]);
    finOverhead(650000);

    Livewire::test(BudgetVsActualReport::class)->assertSee('Plan ('.now()->year.')')->assertSee('Labour')->assertSee('30.0%')->assertDontSee('There is no budget yet')
        ->set('month', 99)->assertSee('Labour');   // a month out of range is clamped, not trusted
});

it('limits the period of the finance reports', function () {
    $this->actingAs(owner());

    expectPeriodLimit(Livewire::test(ProfitabilityReport::class), 'gross margin');
    expectPeriodLimit(Livewire::test(CashFlowReport::class), 'Opening balance');
    expectPeriodLimit(Livewire::test(UnitCostsReport::class), 'Feed made in the period');
});

it('shows the profitability by unit and the cash flow figures on the report pages', function () {
    $this->actingAs(owner());
    dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    app(SyncOperationalPostings::class)();
    finOverhead(300000);

    Livewire::test(ProfitabilityReport::class)->assertSee('Semen production')->assertSee('Administration')->assertSee(MoneyColumn::format(15000000));
    Livewire::test(CashFlowReport::class)->assertSee('Operating expenses')->assertSee(MoneyColumn::format(300000));
    Livewire::test(TrialBalanceReport::class)->assertSee('the books balance')->assertSee('Accounts receivable');
    Livewire::test(ReceivablesPayables::class)->assertSee('Green Acres Farm');
});

it('lets the chart of accounts be extended and cost centres seen, but never deleted once used', function () {
    $this->actingAs(userWithRole('Accountant'));

    Livewire::test(CreateAccount::class)->fillForm(['code' => '5600', 'name' => 'Insurance', 'type' => 'expense'])->call('create')->assertHasNoFormErrors();
    expect(Account::firstWhere('code', '5600')->type->value)->toBe('expense')->and(CostCentre::count())->toBe(10);

    Livewire::test(CreateAccount::class)->fillForm(['code' => '5600', 'name' => 'Duplicate', 'type' => 'expense'])->call('create')->assertHasFormErrors(['code']);
    expect(userWithRole('Accountant')->can('delete', Account::first()))->toBeFalse();
});
