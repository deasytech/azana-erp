<?php

use App\Domain\Backup\Data\StatusCheck as C;
use App\Domain\Finance\Actions\GetTrialBalance;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Traceability\Actions\TraceProduct;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

/**
 * The whole chain at once - boar, semen, sow, litter, pig, feed from purchased raw materials, slaughter, meat, sale - and then
 * the three launch-gate questions: does the stock ledger reconcile, do the books balance, and can the sale be traced to its origin?
 */
it('keeps stock, books and traceability consistent from the semen to the customer\'s invoice', function () {
    $chain = traceChain();
    pay($chain['meatInvoice']->customer, 100000);

    $posted = app(SyncOperationalPostings::class)();

    expect($posted['skipped'])->toBe([])->and($posted['posted'])->toBeGreaterThan(0);

    // Stock: every unit in the ledger is accounted for, and nothing is negative.
    $states = collect(reconciled())->map->state->unique()->values()->all();
    expect($states)->toBe([C::OK])->and(app(GetStockLevels::class)()->every(fn ($row) => bccomp($row->on_hand, '0', 3) > 0))->toBeTrue();

    // Books: debits equal credits, and what the ledger says customers owe is what the invoices less the receipts say.
    $trial = app(GetTrialBalance::class)();
    expect($trial['balanced'])->toBeTrue()->and($trial['debit_minor'])->toBeGreaterThan(0)
        ->and(finBalance('receivables'))->toBe((int) Invoice::sum('total_minor') - 100000);

    // Traceability: the meat on the invoice leads back to the semen and the raw-material supplier, and the pig forward to the customer.
    $trace = app(TraceProduct::class)->forMeatBatch($chain['meat']);
    $nodes = collect($trace['stages'])->flatMap(fn ($s) => $s['nodes']);

    expect($nodes->pluck('type')->unique()->all())->toContain('supplier', 'semen_batch', 'litter', 'carcass', 'invoice', 'customer');
});
