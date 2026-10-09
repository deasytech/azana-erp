<?php

use App\Console\Commands\ReconcileData;
use App\Domain\Backup\Actions\GetOperationsStatus;
use App\Domain\Backup\Data\StatusCheck as C;
use App\Domain\Finance\Actions\ReverseJournal;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Procurement\Actions\RecordSupplierInvoice;
use App\Domain\Procurement\Actions\RecordSupplierPayment;
use App\Domain\Sales\Actions\VoidCustomerPayment;
use App\Enums\PaymentMethod;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

/** A small but complete year: stock in and out, a sale with part payment, a voided receipt, a purchase with part payment. */
function tradingYear(): void
{
    $item = stockItem();
    receiveStock($item, '100', 350, daysAgo: 10);
    receiveStock($item, '40', 400, daysAgo: 5);
    issueStock($item, '120');

    $invoice = dispatched(semenOrder(creditCustomer(), releasedSemen(), 10));
    pay($invoice->customer, 6000000);
    app(VoidCustomerPayment::class)(pay($invoice->customer, 1000000), 'Cheque bounced');

    $order = purchaseOrder();
    receiveGoods($order, ['100']);
    $supplierInvoice = app(RecordSupplierInvoice::class)($order, 'INV-9', now(), 3500000);
    app(RecordSupplierPayment::class)($supplierInvoice, 1000000, now(), PaymentMethod::BankTransfer);

    app(SyncOperationalPostings::class)();
}

it('reconciles an empty system', function () {
    expect(collect(reconciled())->map->state->unique()->values()->all())->toBe([C::OK]);
});

it('reconciles after a year of trading, voids and reversals', function () {
    tradingYear();

    $checks = reconciled();

    expect(collect($checks)->map->state->unique()->values()->all())->toBe([C::OK])
        ->and(array_keys($checks))->toBe(['Stock ledger', 'Journal balance', 'Receivables', 'Payables', 'Mobile sync']);
});

it('also reconciles a reversed manual entry and a reversed document entry', function () {
    tradingYear();
    app(ReverseJournal::class)(finPost('cash', 'sales_pigs', 500000), 'Typing mistake');

    expect(reconciled()['Journal balance']->state)->toBe(C::OK);
});

it('catches stock that no longer agrees with the ledger', function () {
    tradingYear();
    DB::table('inventory_layers')->limit(1)->update(['remaining_quantity' => DB::raw('remaining_quantity + 1')]);

    expect(reconciled()['Stock ledger']->state)->toBe(C::FAILED)->and(reconciled()['Stock ledger']->detail)->toContain('differs');
});

it('catches a stock layer holding more than was ever received', function () {
    tradingYear();
    DB::table('inventory_layers')->limit(1)->update(['quantity' => 0]);

    expect(reconciled()['Stock ledger']->state)->toBe(C::FAILED);
});

it('catches a journal entry whose lines were changed after posting', function () {
    tradingYear();
    DB::table('journal_lines')->where('journal_entry_id', JournalEntry::firstOrFail()->id)->limit(1)->update(['debit_minor' => DB::raw('debit_minor + 5')]);

    expect(reconciled()['Journal balance']->state)->toBe(C::FAILED);
});

it('catches receivables that no longer match the invoices behind them', function () {
    tradingYear();
    DB::table('invoices')->limit(1)->increment('total_minor', 100);

    expect(reconciled()['Receivables']->state)->toBe(C::FAILED);
});

it('catches payables that no longer match the supplier invoices behind them', function () {
    tradingYear();
    DB::table('supplier_payments')->limit(1)->increment('amount_minor', 100);

    expect(reconciled()['Payables']->state)->toBe(C::FAILED);
});

it('warns, rather than fails, about documents waiting to be posted', function () {
    $invoice = dispatched(semenOrder(creditCustomer(), releasedSemen(), 2));

    expect(reconciled()['Receivables']->state)->toBe(C::WARNING)->and(reconciled()['Receivables']->detail)->toContain('waiting to be posted');

    app(SyncOperationalPostings::class)();

    expect(reconciled()['Receivables']->state)->toBe(C::OK)->and($invoice->exists)->toBeTrue();
});

it('warns about mobile changes that have been failing for days', function () {
    DB::table('sync_mutations')->insert([
        'client_id' => (string) Str::uuid(), 'device_id' => 'phone-1', 'user_id' => owner()->id, 'type' => 'weight', 'payload' => '{}', 'status' => 'failed',
        'attempted_at' => now()->subDays(3), 'occurred_at' => now()->subDays(3), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(reconciled()['Mobile sync']->state)->toBe(C::WARNING);
});

it('stores the verdict for the monitor and fails the command when the books disagree', function () {
    tradingYear();

    $this->artisan('erp:reconcile')->assertSuccessful();
    $status = collect(app(GetOperationsStatus::class)())->firstWhere('name', 'Reconciliation');

    expect($status->state)->toBe(C::OK);

    DB::table('journal_lines')->where('journal_entry_id', JournalEntry::firstOrFail()->id)->limit(1)->update(['credit_minor' => DB::raw('credit_minor + 5')]);

    $this->artisan('erp:reconcile')->assertFailed();

    expect(collect(app(GetOperationsStatus::class)())->firstWhere('name', 'Reconciliation')->state)->toBe(C::FAILED);
});

it('tells the monitor when the books have never been reconciled or the check is overdue', function () {
    $status = fn () => collect(app(GetOperationsStatus::class)())->firstWhere('name', 'Reconciliation');

    expect($status()->state)->toBe(C::WARNING);

    Cache::put(ReconcileData::LAST_RESULT, ['at' => now()->subHours(40)->toIso8601String(), 'checks' => []], now()->addDay());

    expect($status()->state)->toBe(C::WARNING)->and($status()->detail)->toContain('40 hours');
});
