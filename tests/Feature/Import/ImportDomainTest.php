<?php

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\Breed;
use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Import\Actions\CheckImport;
use App\Domain\Import\Actions\ImportRegistry;
use App\Domain\Import\Models\DataImport;
use App\Domain\Import\Support\ImportFile;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
    $this->boss = owner();
    $this->actingAs($this->boss);
});

const OPENING_DAY = '2026-10-01';
const ADA_EMAIL = 'ada@example.com';
const SALE_DAY = '2026-06-01';
const CUSTOMER_HEAD = ['name', 'customer_type', 'phone', 'email'];
const PIG_HEAD = ['sex', 'category', 'birth_date', 'breed', 'source', 'pen', 'ear_tag', 'sire', 'dam', 'weight_kg', 'weight_date'];
const SALE_HEAD = ['reference', 'customer', 'sale_date', 'product', 'description', 'unit', 'quantity', 'unit_price', 'amount_paid', 'payment_method'];

describe('checking a file', function () {
    it('saves nothing and consumes no numbers', function () {
        $import = csvImport('customers', [CUSTOMER_HEAD, ['Ada Farms', 'farmer', '0803', ADA_EMAIL]]);

        expect($import->error_rows)->toBe(0)->and($import->total_rows)->toBe(1)->and($import->isReady())->toBeTrue()
            ->and(Customer::count())->toBe(0)
            ->and(DB::table('number_sequences')->where('key', 'customer')->exists())->toBeFalse();
    });

    it('reports each failed row with the reason and keeps the good ones passing', function () {
        $import = csvImport('customers', [
            CUSTOMER_HEAD,
            ['Ada Farms', 'farmer', '0803', ADA_EMAIL],
            ['', 'farmer', '', ''],
            ['Bad Type Ltd', 'spaceship', '', ''],
            ['Bad Mail', 'farmer', '', 'not-an-email'],
            ['ADA FARMS', 'farmer', '', ''],
        ]);

        $rows = $import->rows()->orderBy('row_number')->get();

        expect($import->error_rows)->toBe(4)->and($import->isReady())->toBeFalse()
            ->and($rows[0]->error)->toBeNull()
            ->and($rows[1]->error)->toContain('name is required')
            ->and($rows[2]->error)->toContain('not a known customer type')
            ->and($rows[3]->error)->toContain('not valid')
            ->and($rows[4]->error)->toContain('not overwritten')
            ->and($rows[0]->row_number)->toBe(2);
    });

    it('refuses a file with the wrong or missing headings before looking at any row', function () {
        expect(fn () => csvImport('customers', [['name'], ['Ada']]))->toThrow(DomainException::class, 'missing the column')
            ->and(fn () => csvImport('customers', [[...CUSTOMER_HEAD, 'shoe_size'], ['Ada', 'farmer', '', '', '9']]))->toThrow(DomainException::class, 'Unknown column')
            ->and(fn () => csvImport('customers', [CUSTOMER_HEAD]))->toThrow(DomainException::class, 'no data rows')
            ->and(DataImport::count())->toBe(0);
    });

    it('accepts headings in any case and spacing, and reads Excel files', function () {
        $csv = csvImport('customers', [['Name', 'Customer Type'], ['Ada Farms', 'farmer']]);
        expect($csv->error_rows)->toBe(0);

        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['name', 'customer_type']));
        $writer->addRow(Row::fromValues(['Excel Farms', 'farmer']));
        $writer->close();

        $xlsx = app(CheckImport::class)('customers', $path, 'customers.xlsx', $this->boss);
        @unlink($path);

        expect($xlsx->error_rows)->toBe(0)->and($xlsx->total_rows)->toBe(1);
    });

    it('stops people who may not create that data', function () {
        $worker = farmWorker();
        $this->actingAs($worker);

        expect(fn () => csvImport('customers', [CUSTOMER_HEAD, ['Ada', 'farmer', '', '']]))->toThrow(DomainException::class, 'not allowed')
            ->and(array_keys(app(ImportRegistry::class)->allowedFor($worker)))->toBe([])
            ->and(array_keys(app(ImportRegistry::class)->allowedFor($this->boss)))->toHaveCount(6);
    });

    it('offers a template with the exact headings for every kind', function () {
        foreach (app(ImportRegistry::class)->all() as $importer) {
            $csv = app(ImportFile::class)->template($importer, 'csv');
            expect(trim(ltrim($csv, "\xEF\xBB\xBF")))->toBe(implode(',', $importer->columnNames()))
                ->and(strlen(app(ImportFile::class)->template($importer, 'xlsx')))->toBeGreaterThan(500)
                ->and(array_filter($importer->columns(), fn ($c) => $c->help === ''))->toBe([]);
        }
    });
});

describe('committing', function () {
    it('saves every row through the real action and marks the import done', function () {
        $import = csvImport('customers', [CUSTOMER_HEAD, ['Ada Farms', 'farmer', '0803', ADA_EMAIL], ['Bayo Ltd', 'farmer', '', '']]);

        commit($import);

        expect(Customer::orderBy('id')->pluck('code')->all())->toBe(['C-000001', 'C-000002'])
            ->and(Customer::firstWhere('name', 'Ada Farms')->credit_status->value)->toBe('none')
            ->and($import->fresh()->status)->toBe(DataImport::COMMITTED)->and($import->fresh()->committed_by)->toBe($this->boss->id)
            ->and(DB::table('audit_logs')->where('event', 'imported')->count())->toBe(1);
    });

    it('cannot commit a file with problems, or commit twice', function () {
        $bad = csvImport('customers', [CUSTOMER_HEAD, ['', 'farmer', '', '']]);
        expect(fn () => commit($bad))->toThrow(DomainException::class, 'Fix the failed rows');

        $good = csvImport('customers', [CUSTOMER_HEAD, ['Ada Farms', 'farmer', '', '']]);
        commit($good);

        expect(fn () => commit($good))->toThrow(DomainException::class, 'already committed')
            ->and(Customer::count())->toBe(1);
    });

    it('imports nothing when the data changed after the check', function () {
        $import = csvImport('customers', [CUSTOMER_HEAD, ['Ada Farms', 'farmer', '', ''], ['Bayo Ltd', 'farmer', '', '']]);
        customer(['name' => 'Bayo Ltd']);

        expect(fn () => commit($import))->toThrow(DomainException::class, 'no longer passes')
            ->and(Customer::count())->toBe(1)
            ->and($import->fresh()->status)->toBe(DataImport::CHECKED)
            ->and($import->fresh()->error_rows)->toBe(1)
            ->and($import->rows()->whereNotNull('error')->first()->row_number)->toBe(3);
    });

    it('needs the approve permission, not only the right to create', function () {
        $import = csvImport('customers', [CUSTOMER_HEAD, ['Ada Farms', 'farmer', '', '']]);
        $salesOfficer = userWithRole('Sales Officer');

        expect(fn () => commit($import, $salesOfficer))->toThrow(DomainException::class, 'not allowed')
            ->and(Customer::count())->toBe(0);
    });

    it('produces a downloadable report of the failed rows for correction', function () {
        $import = csvImport('customers', [CUSTOMER_HEAD, ['Ada Farms', 'farmer', '', ''], ['', 'farmer', '', ''], ['=HYPERLINK("x")', 'nope', '', '']]);

        $csv = app(ImportFile::class)->errorReport($import->rows()->whereNotNull('error')->orderBy('row_number')->get(), CUSTOMER_HEAD);

        expect($csv)->toContain('sheet_row,problem,name,customer_type,phone,email')
            ->and($csv)->toContain('3,"name is required."')
            ->and($csv)->toContain("'=HYPERLINK")      // a formula in the data is kept as text
            ->and($csv)->not->toContain('Ada Farms');
    });
});

describe('suppliers', function () {
    it('creates them with the next code and refuses duplicates inside the file', function () {
        $import = csvImport('suppliers', [['name', 'email', 'payment_terms_days'], ['AgroMix', 'a@x.com', '30'], ['agromix', '', ''], ['Other', '', '9999']]);

        expect($import->rows()->orderBy('row_number')->pluck('error')->all())->toBe([null, 'name matches the existing supplier SUP-0001 (AgroMix), by code, name or e-mail. Existing suppliers are not overwritten.', 'payment_terms_days must be at most 365.']);

        $good = csvImport('suppliers', [['name', 'payment_terms_days'], ['AgroMix', '30'], ['Feedco', '']]);
        commit($good);

        expect(Supplier::orderBy('id')->pluck('code')->all())->toBe(['SUP-0001', 'SUP-0002']);
    });
});

describe('historical pigs', function () {
    it('registers each pig with its tag, pen, parents and last weight', function () {
        $pen = newPen('PEN-9');
        $breed = Breed::first();

        $import = csvImport('animals', [
            PIG_HEAD,
            ['male', 'boar', '2023-01-10', $breed->name, 'purchased', 'PEN-9', 'T-1', '', '', '240.50', '2026-09-01'],
            ['female', 'sow', '2023-02-11', $breed->code, '', 'PEN-9', 'T-2', 'T-1', '', '', ''],
        ]);
        expect($import->error_rows)->toBe(0)->and(Animal::count())->toBe(0);

        commit($import);

        $sire = Animal::firstWhere('animal_number', 'like', '%BOAR%');
        $sow = Animal::firstWhere('animal_number', 'like', '%SOW%');

        expect($sire->identifiers()->where('value', 'T-1')->exists())->toBeTrue()
            ->and($sire->weights()->first()->weight_kg)->toBe('240.50')
            ->and($sow->parentage->sire_id)->toBe($sire->id)
            ->and($sow->current_pen_id)->toBe($pen->id);
    });

    it('rejects a tag already in use, an unknown parent and half a weight', function () {
        register()->identifiers()->create(['type' => 'ear_tag', 'value' => 'T-1']);

        $import = csvImport('animals', [
            PIG_HEAD,
            ['female', 'sow', '', '', '', '', 'T-1', '', '', '', ''],
            ['female', 'sow', '', '', '', '', 'T-3', 'GHOST', '', '', ''],
            ['female', 'sow', '', '', '', '', 'T-4', '', '', '120', ''],
            ['male', 'sow', '', '', '', '', 'T-5', '', '', '', ''],
        ]);

        $errors = $import->rows()->orderBy('row_number')->pluck('error')->all();

        expect($errors[0])->not->toBeNull()
            ->and($errors[1])->toContain('not an animal already registered')
            ->and($errors[2])->toContain('go together')
            ->and($errors[3])->toContain('cannot be registered');
    });
});

describe('inventory opening balances', function () {
    it('posts opening ledger entries once per item, store and batch', function () {
        stockItem('MAIZE');
        store('MAIN');
        $head = ['item', 'store', 'quantity', 'unit_cost', 'as_of'];

        $import = csvImport('inventory_opening', [$head, ['MAIZE', 'MAIN', '1250.5', '420.00', OPENING_DAY]]);
        expect($import->error_rows)->toBe(0)->and(InventoryTransaction::count())->toBe(0);

        commit($import);

        $line = InventoryTransaction::first();
        expect($line->type)->toBe(InventoryTransactionType::Opening)->and((string) $line->quantity)->toBe('1250.500')
            ->and($line->value_minor)->toBe(52521000);

        $again = csvImport('inventory_opening', [$head, ['MAIZE', 'MAIN', '10', '420.00', '2026-10-02']]);
        expect($again->rows()->first()->error)->toContain('already has an opening balance');
    });

    it('asks for the batch and expiry of items that need them', function () {
        stockItem('VAX', ['tracks_batches' => true, 'tracks_expiry' => true]);
        store('MAIN');

        $import = csvImport('inventory_opening', [
            ['item', 'store', 'quantity', 'unit_cost', 'as_of', 'batch_number', 'expiry_date'],
            ['VAX', 'MAIN', '10', '100', OPENING_DAY, '', ''],
            ['VAX', 'MAIN', '10', '100', OPENING_DAY, 'L1', ''],
            ['VAX', 'MAIN', '10', '100', OPENING_DAY, 'L2', '2027-03-01'],
            ['VAX', 'MAIN', '10', '100', '2099-01-01', 'L3', '2100-03-01'],
            ['VAX', 'MAIN', '-5', '100', OPENING_DAY, 'L4', '2027-03-01'],
        ]);

        $errors = $import->rows()->orderBy('row_number')->pluck('error')->all();

        expect($errors[0])->toContain('tracked by batch')->and($errors[1])->toContain('expiry')->and($errors[2])->toBeNull()
            ->and($errors[3])->toContain('future')->and($errors[4])->toContain('must be a number');
    });
});

describe('feed formulas', function () {
    it('groups ingredient rows into a draft formula', function () {
        stockItem('MAIZE');
        stockItem('SOYA');
        $type = FeedType::first() ?? FeedType::create(['code' => 'GRW', 'name' => 'Grower']);

        $import = csvImport('feed_formulas', [
            ['formula_code', 'formula_name', 'feed_type', 'ingredient', 'inclusion_percent', 'crude_protein_percent'],
            ['ff-1', 'Grower mix', $type->name, 'MAIZE', '70', '16'],
            ['ff-1', '', '', 'SOYA', '30', ''],
            ['ff-2', 'Bad mix', $type->name, 'MAIZE', '120', ''],
        ]);

        expect($import->rows()->orderBy('row_number')->pluck('error')->all())->toBe([null, null, 'Each inclusion must be above 0 and at most 100 percent, with at most 4 decimals.']);

        $fixed = csvImport('feed_formulas', [
            ['formula_code', 'formula_name', 'feed_type', 'ingredient', 'inclusion_percent'],
            ['ff-1', 'Grower mix', $type->name, 'MAIZE', '70'],
            ['ff-1', '', '', 'SOYA', '30'],
        ]);
        commit($fixed);

        $formula = FeedFormula::firstWhere('code', 'FF-1');
        expect($formula->items)->toHaveCount(2)->and($formula->isDraft())->toBeTrue();
    });
});

describe('historical sales', function () {
    it('records sales, invoices and receipts that count for balances but not for stock or the ledger', function () {
        $buyer = customer(['name' => 'Green Acres Farm']);
        $before = InventoryTransaction::count();

        $import = csvImport('historical_sales', [
            SALE_HEAD,
            ['OLD-1', $buyer->code, SALE_DAY, 'pigs', '20 finishers', 'head', '20', '85000.00', '1000000.00', 'bank_transfer'],
        ]);
        expect($import->error_rows)->toBe(0)->and(Invoice::count())->toBe(0);
        commit($import);

        $invoice = Invoice::first();
        expect($invoice->is_historical)->toBeTrue()->and($invoice->total_minor)->toBe(170000000)
            ->and($invoice->paidMinor())->toBe(100000000)->and($invoice->balanceMinor())->toBe(70000000)
            ->and($invoice->isOverdue())->toBeTrue()
            ->and(InventoryTransaction::count())->toBe($before);

        app(SyncOperationalPostings::class)();
        expect(JournalEntry::where('source_key', 'like', 'invoice:%')->orWhere('source_key', 'like', 'payment:%')->count())->toBe(0);
    });

    it('imports the same old reference only once and checks the money', function () {
        $buyer = customer();
        commit(csvImport('historical_sales', [SALE_HEAD, ['OLD-1', $buyer->code, SALE_DAY, 'pigs', 'x', 'head', '2', '1000', '', '']]));

        $import = csvImport('historical_sales', [
            SALE_HEAD,
            ['OLD-1', $buyer->code, SALE_DAY, 'pigs', 'x', 'head', '2', '1000', '', ''],
            ['OLD-2', $buyer->code, SALE_DAY, 'pigs', 'x', 'head', '2.5', '1000', '', ''],
            ['OLD-3', $buyer->code, SALE_DAY, 'pigs', 'x', 'head', '2', '1000', '5000', 'cash'],
            ['OLD-4', $buyer->code, SALE_DAY, 'pigs', 'x', 'head', '2', '1000', '500', ''],
            ['OLD-5', $buyer->code, '2099-06-01', 'meat', 'x', 'kg', '2', '1000', '', ''],
            ['OLD-6', 'nobody', SALE_DAY, 'meat', 'x', 'kg', '2', '1000', '', ''],
        ]);

        $errors = $import->rows()->orderBy('row_number')->pluck('error')->all();

        expect($errors[0])->toContain('already imported')->and($errors[1])->toContain('whole number')
            ->and($errors[2])->toContain('more than the sale')->and($errors[3])->toContain('payment_method')
            ->and($errors[4])->toContain('future')->and($errors[5])->toContain('not a known customer');
    });
});
