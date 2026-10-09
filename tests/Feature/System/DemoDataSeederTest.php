<?php

use App\Domain\Animal\Models\Animal;
use App\Domain\Backup\Actions\ReconcileLedgers;
use App\Domain\Backup\Data\StatusCheck as C;
use App\Domain\Finance\Actions\GetTrialBalance;
use App\Domain\Sales\Models\Invoice;
use App\Domain\System\Actions\ResetToLiveData;
use App\Enums\DataMode;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;

it('loads three months of consistent practice data that the go-live reset can remove again', function () {
    $this->seed(DemoDataSeeder::class);

    foreach (['animals', 'litters', 'breeding_services', 'semen_batches', 'feed_production_batches', 'carcasses', 'meat_production_batches', 'invoices', 'payments', 'purchase_orders', 'supplier_invoices', 'journal_entries', 'tasks'] as $table) {
        expect(DB::table($table)->count())->toBeGreaterThan(0, "no {$table} were made");
    }

    // The books, stock and ledgers agree with the documents, exactly as the nightly reconciliation checks.
    expect(collect(app(ReconcileLedgers::class)())->map->state->unique()->values()->all())->toBe([C::OK])
        ->and(app(GetTrialBalance::class)()['balanced'])->toBeTrue()
        ->and(Invoice::min('issued_on'))->toBeLessThan(now()->subDays(60)->toDateString());

    expect(app(ResetToLiveData::class)->mode())->toBe(DataMode::Demo);

    app(ResetToLiveData::class)(owner(), false);

    expect(Animal::count())->toBe(0)->and(Invoice::count())->toBe(0)->and(app(ResetToLiveData::class)->mode())->toBe(DataMode::Live);
});

it('will not run on a database that already holds animals', function () {
    $this->seed(DemoDataSeeder::class);
    $animals = Animal::count();

    $this->seed(DemoDataSeeder::class);

    expect(Animal::count())->toBe($animals);
});
