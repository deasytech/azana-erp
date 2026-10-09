<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Animals\Pages\ListAnimals;
use App\Filament\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

/** How many queries a callback runs. */
function queriesDuring(Closure $callback): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    $callback();

    return $count;
}

it('runs the same number of queries for a list of 3 rows as for a list of 12 (no N+1)', function (string $page, Closure $addRow) {
    $this->actingAs(owner());

    for ($i = 0; $i < 3; $i++) {
        $addRow($i);
    }
    Livewire::test($page)->assertSuccessful();   // warm caches
    $few = queriesDuring(fn () => Livewire::test($page)->assertSuccessful());

    for ($i = 3; $i < 12; $i++) {
        $addRow($i);
    }
    $many = queriesDuring(fn () => Livewire::test($page)->assertSuccessful());

    expect($many)->toBeLessThanOrEqual($few + 1, "{$page}: {$few} queries for 3 rows but {$many} for 12");
})->with([
    'animals' => [ListAnimals::class, fn (int $i) => register()],
    'invoices' => [ListInvoices::class, fn (int $i) => dispatched(semenOrder(creditCustomer(), releasedSemen(), 1))],
    'stock ledger' => [ListInventoryTransactions::class, fn (int $i) => receiveStock(stockItem("ITEM{$i}"), '10', 100)],
]);

it('opens the home dashboard in a bounded number of queries, however much data there is', function () {
    $this->actingAs(owner());
    scenario();
    Livewire::test(Dashboard::class)->assertSuccessful();

    $queries = queriesDuring(fn () => Livewire::test(Dashboard::class)->assertSuccessful());

    expect($queries)->toBeLessThan(150);
});

it('indexes the date columns that reports and calendars select by', function (string $table, string $column) {
    $indexed = collect(Schema::getIndexes($table))->contains(fn (array $index) => ($index['columns'][0] ?? null) === $column);

    expect($indexed)->toBeTrue("{$table}.{$column} needs an index");
})->with([
    ['feed_consumption_records', 'consumed_on'], ['weight_records', 'weighed_at'], ['treatments', 'administered_on'], ['vaccinations', 'administered_on'],
    ['breeding_services', 'serviced_on'], ['farrowings', 'farrowed_on'], ['animal_movements', 'moved_at'], ['production_costs', 'incurred_on'],
    ['sales_orders', 'dispatched_on'], ['supplier_invoices', 'invoice_date'], ['carcasses', 'slaughtered_at'], ['journal_entries', 'entry_date'],
]);
