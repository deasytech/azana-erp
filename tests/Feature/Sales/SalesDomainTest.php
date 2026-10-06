<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\PriceList;
use App\Domain\Farm\Models\PriceListItem;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Health\Actions\StartQuarantine;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Sales\Actions\AllocateCustomerFunds;
use App\Domain\Sales\Actions\CancelSalesOrder;
use App\Domain\Sales\Actions\ConfirmSalesOrder;
use App\Domain\Sales\Actions\CreateSalesOrder;
use App\Domain\Sales\Actions\DispatchSalesOrder;
use App\Domain\Sales\Actions\GetCustomerAccount;
use App\Domain\Sales\Actions\GetCustomerHistory;
use App\Domain\Sales\Actions\GetOutstandingBalances;
use App\Domain\Sales\Actions\RecordCustomerPayment;
use App\Domain\Sales\Actions\SaveCustomer;
use App\Domain\Sales\Actions\SetCustomerCredit;
use App\Domain\Sales\Actions\VoidCustomerPayment;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Semen\Actions\ManageSemenBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\CreditStatus;
use App\Enums\InventoryTransactionType as T;
use App\Enums\LookupCategory;
use App\Enums\QuarantineType;
use App\Enums\ReceiptMethod;
use App\Enums\ReservationStatus;
use App\Enums\SalesOrderStatus as S;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    $this->manager = userWithRole('Farm Manager');
    $this->officer = userWithRole('Sales Officer');
});

function doses(): string
{
    return app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'SEMEN-DUR')->id);
}

describe('customers and credit', function () {
    it('creates a numbered customer on cash terms', function () {
        $customer = customer(['email' => 'a@b.test']);

        expect($customer->code)->toBe('C-000001')->and($customer->credit_status)->toBe(CreditStatus::None)->and($customer->credit_limit_minor)->toBe(0)
            ->and(customer(['name' => 'Second'])->code)->toBe('C-000002');

        app(SaveCustomer::class)(['name' => 'Renamed', 'customer_type_id' => $customer->customer_type_id], $customer);
        expect($customer->fresh()->name)->toBe('Renamed');
    });

    it('checks the details', function () {
        $save = app(SaveCustomer::class);
        $type = lookup(LookupCategory::CustomerType, 'farmer');

        expect(fn () => $save(['name' => ' ', 'customer_type_id' => $type]))->toThrow(DomainException::class, 'name');
        expect(fn () => $save(['name' => 'X', 'customer_type_id' => 99999]))->toThrow(DomainException::class, 'customer type');
        expect(fn () => $save(['name' => 'X', 'customer_type_id' => $type, 'email' => 'nope']))->toThrow(DomainException::class, 'email');
    });

    it('approves credit only for someone who may, and not for the person who set the customer up', function () {
        $customer = app(SaveCustomer::class)(['name' => 'Acme', 'customer_type_id' => lookup(LookupCategory::CustomerType, 'retailer')], null, $this->manager);
        $set = app(SetCustomerCredit::class);

        expect(fn () => $set($customer, CreditStatus::Approved, 5000000, 30, $this->officer))->toThrow(DomainException::class, 'not authorised');
        expect(fn () => $set($customer, CreditStatus::Approved, 5000000, 30, $this->manager))->toThrow(DomainException::class, 'someone other than');
        expect(fn () => $set($customer, CreditStatus::Approved, 0, 30, userWithRole('General Manager')))->toThrow(DomainException::class, 'limit above zero');

        $approved = $set($customer, CreditStatus::Approved, 5000000, 30, userWithRole('General Manager'));
        expect($approved->credit_status)->toBe(CreditStatus::Approved)->and($approved->credit_limit_minor)->toBe(5000000)->and($approved->payment_terms_days)->toBe(30)
            ->and($approved->credit_approved_by)->not->toBeNull();
    });

    it('works out outstanding, overdue, deposit and headroom', function () {
        $customer = creditCustomer(20000000, 30);
        dispatched(semenOrder($customer, releasedSemen(), 10, ['discount_percent' => 10]), 40);   // 13,500,000, due 10 days ago
        pay($customer, 2000000, [], 0);   // a deposit: nothing named, so it is not applied... (named empty array)

        $account = app(GetCustomerAccount::class)($customer->fresh());

        expect($account)->toMatchArray(['outstanding' => 13500000, 'overdue' => 13500000, 'deposit' => 2000000, 'credit_limit' => 20000000, 'headroom' => 8500000]);
    });
});

describe('orders', function () {
    it('totals each line with its discount in whole minor units', function () {
        $batch = releasedSemen();
        $animal = register(['category_id' => categoryId('grower')]);

        $order = app(CreateSalesOrder::class)(customer(), [
            ['kind' => 'semen', 'semen_batch_id' => $batch->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => 10, 'unit_price_minor' => 1500000, 'discount_percent' => 10],
            ['kind' => 'pig_animal', 'animal_id' => $animal->id, 'unit' => 'kg', 'quantity' => '110.5', 'unit_price_minor' => 120000],
        ], now());

        expect($order->number)->toBe('SO-000001')->and($order->status)->toBe(S::Draft)->and($order->currency_code)->toBe('NGN')
            ->and($order->lines->pluck('line_total_minor')->all())->toBe([13500000, 13260000])->and($order->total_minor)->toBe(26760000)
            ->and($order->lines[0]->description)->toContain($batch->number)->and($order->lines[1]->heads)->toBe(1);
    });

    it('takes the semen price from the active price list when none is given', function () {
        $batch = releasedSemen();
        $list = PriceList::create(['farm_id' => Farm::first()->id, 'category_id' => lookup(LookupCategory::PriceCategory, 'semen'), 'code' => 'SEM', 'name' => 'Semen', 'currency_code' => 'NGN', 'is_active' => true]);
        PriceListItem::create(['price_list_id' => $list->id, 'code' => 'DUR', 'description' => 'Duroc', 'unit_id' => UnitOfMeasure::firstWhere('code', 'DOSE')->id, 'unit_price_minor' => 1200000, 'inventory_item_id' => InventoryItem::firstWhere('code', 'SEMEN-DUR')->id]);

        $order = app(CreateSalesOrder::class)(customer(), [['kind' => 'semen', 'semen_batch_id' => $batch->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => 5]], now());

        expect($order->total_minor)->toBe(6000000);
    });

    it('prices pigs from a batch by head or by weight', function () {
        $batch = openBatch(['count' => 50]);
        $create = fn (array $line) => app(CreateSalesOrder::class)(customer(), [$line + ['kind' => 'pig_batch', 'production_batch_id' => $batch->id, 'heads' => 4, 'unit_price_minor' => 5000000]], now());

        expect($create(['unit' => 'head'])->total_minor)->toBe(20000000);
        expect($create(['unit' => 'kg', 'quantity' => '420.5', 'unit_price_minor' => 130000])->total_minor)->toBe(54665000);
    });

    it('checks every line', function () {
        $batch = releasedSemen();
        $store = store('SEMEN')->id;
        $create = fn (array $line, $c = null) => app(CreateSalesOrder::class)($c ?? customer(), [$line], now());
        $ok = ['kind' => 'semen', 'semen_batch_id' => $batch->id, 'inventory_location_id' => $store, 'quantity' => 5, 'unit_price_minor' => 100];

        expect(fn () => app(CreateSalesOrder::class)(customer(), [], now()))->toThrow(DomainException::class, 'at least one line');
        expect(fn () => $create(['kind' => 'cheese']))->toThrow(DomainException::class, 'what each line sells');
        expect(fn () => $create(['quantity' => 2.5] + $ok))->toThrow(DomainException::class, 'whole doses');
        expect(fn () => $create(['unit_price_minor' => null] + $ok))->toThrow(DomainException::class, 'price');
        expect(fn () => $create(['unit_price_minor' => 12.5] + $ok))->toThrow(DomainException::class, 'price');
        expect(fn () => $create(['discount_percent' => 101] + $ok))->toThrow(DomainException::class, 'percentage');
        expect(fn () => $create(['kind' => 'pig_animal', 'animal_id' => register()->id, 'unit' => 'kg', 'quantity' => '0', 'unit_price_minor' => 1]))->toThrow(DomainException::class, 'live weight');
        expect(fn () => app(CreateSalesOrder::class)(customer(), [$ok, $ok], now()))->toThrow(DomainException::class, 'combine');
        expect(fn () => app(CreateSalesOrder::class)(customer(), [$ok], now()->addDay()))->toThrow(DomainException::class, 'future');

        $closed = customer(['name' => 'Gone', 'is_active' => false]);
        expect(fn () => $create($ok, $closed))->toThrow(DomainException::class, 'not an active customer');
        expect(SalesOrderCount())->toBe(0);
    });

    it('can be repeated safely with an idempotency key', function () {
        $batch = releasedSemen();
        $customer = customer();
        $line = [['kind' => 'semen', 'semen_batch_id' => $batch->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => 2, 'unit_price_minor' => 100]];

        $a = app(CreateSalesOrder::class)($customer, $line, now(), null, null, 'k1');
        $b = app(CreateSalesOrder::class)($customer, $line, now(), null, null, 'k1');

        expect($b->id)->toBe($a->id)->and(SalesOrderCount())->toBe(1);
        expect(fn () => app(CreateSalesOrder::class)(customer(['name' => 'Other']), $line, now(), null, null, 'k1'))->toThrow(DomainException::class, 'idempotency');
    });
});

function SalesOrderCount(): int
{
    return SalesOrder::count();
}

describe('confirming and reserving', function () {
    it('reserves the doses so another order cannot take them', function () {
        $batch = releasedSemen();   // 24 doses
        $first = confirmed(semenOrder(creditCustomer(), $batch, 20));

        expect($first->status)->toBe(S::Confirmed)->and($first->lines->first()->reservations()->sole()->status)->toBe(ReservationStatus::Active);
        expect(doses())->toBe('24.000');   // nothing has left stock yet

        $second = semenOrder(creditCustomer(over: ['name' => 'Second']), $batch, 5);
        expect(fn () => app(ConfirmSalesOrder::class)($second, $this->manager))->toThrow(DomainException::class, 'only 4 doses are free');

        $fits = semenOrder(creditCustomer(over: ['name' => 'Third']), $batch, 4);
        expect(app(ConfirmSalesOrder::class)($fits, $this->manager)->status)->toBe(S::Confirmed);
    });

    it('gives the doses back when an order is cancelled', function () {
        $batch = releasedSemen();
        $order = confirmed(semenOrder(creditCustomer(), $batch, 24));

        app(CancelSalesOrder::class)($order, 'Customer changed their mind');

        expect($order->fresh()->status)->toBe(S::Cancelled)->and($order->lines->first()->reservations()->sole()->status)->toBe(ReservationStatus::Released);
        expect(app(ConfirmSalesOrder::class)(semenOrder(creditCustomer(over: ['name' => 'B']), $batch, 24), $this->manager)->status)->toBe(S::Confirmed);
        expect(fn () => app(CancelSalesOrder::class)($order, 'Again'))->toThrow(DomainException::class, 'no longer be cancelled');
        expect(fn () => app(CancelSalesOrder::class)(semenOrder(customer(), $batch), ' '))->toThrow(DomainException::class, 'reason');
    });

    it('will not confirm semen that cannot be sold', function () {
        $batch = releasedSemen();
        app(ManageSemenBatch::class)->quarantine($batch, 'Recall');

        expect(fn () => app(ConfirmSalesOrder::class)(semenOrder(creditCustomer(), $batch, 2), $this->manager))->toThrow(DomainException::class, 'cannot be sold or used');

        $draft = collectSemen(semenBoar());
        expect(fn () => app(CreateSalesOrder::class)(customer(), [['kind' => 'semen', 'semen_batch_id' => $draft->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => 1, 'unit_price_minor' => 1]], now()))->not->toThrow(DomainException::class);
    });

    it('will not confirm a pig that is under withdrawal, in quarantine or already reserved', function () {
        $pig = register(['category_id' => categoryId('grower')]);
        $line = ['kind' => 'pig_animal', 'animal_id' => $pig->id, 'unit' => 'head', 'unit_price_minor' => 5000000];
        $order = fn () => app(CreateSalesOrder::class)(creditCustomer(over: ['name' => 'N'.uniqid()]), [$line], now());

        $first = confirmed($order());
        expect(fn () => app(ConfirmSalesOrder::class)($order(), $this->manager))->toThrow(DomainException::class, 'already reserved');
        app(CancelSalesOrder::class)($first, 'Released');

        treat($pig, medicine(30), now()->subDays(2)->toDateString());
        expect(fn () => app(ConfirmSalesOrder::class)($order(), $this->manager))->toThrow(DomainException::class, 'under withdrawal');

        $other = register(['category_id' => categoryId('grower')]);
        app(StartQuarantine::class)($other, QuarantineType::Isolation, now()->startOfDay(), 'Coughing');
        expect(fn () => app(ConfirmSalesOrder::class)(app(CreateSalesOrder::class)(creditCustomer(over: ['name' => 'Q']), [['animal_id' => $other->id] + $line], now()), $this->manager))->toThrow(DomainException::class, 'cannot be sold');
    });

    it('reserves heads of a batch and counts what others hold', function () {
        $batch = openBatch(['count' => 10]);
        $order = fn (int $heads, string $name) => app(CreateSalesOrder::class)(creditCustomer(over: ['name' => $name]), [['kind' => 'pig_batch', 'production_batch_id' => $batch->id, 'heads' => $heads, 'unit' => 'head', 'unit_price_minor' => 5000000]], now());

        confirmed($order(8, 'A'));
        expect(fn () => app(ConfirmSalesOrder::class)($order(3, 'B'), $this->manager))->toThrow(DomainException::class, 'only 2 pigs are free');
        expect(app(ConfirmSalesOrder::class)($order(2, 'C'), $this->manager)->status)->toBe(S::Confirmed);
    });

    it('needs approval for a discount above the limit', function () {
        $batch = releasedSemen();
        $customer = creditCustomer();

        expect(fn () => app(ConfirmSalesOrder::class)(semenOrder($customer, $batch, 2, ['discount_percent' => 10.01]), $this->officer))->toThrow(DomainException::class, 'someone who can approve');
        expect(app(ConfirmSalesOrder::class)(semenOrder($customer, $batch, 2, ['discount_percent' => 10]), $this->officer)->status)->toBe(S::Confirmed);
        expect(app(ConfirmSalesOrder::class)(semenOrder($customer, $batch, 2, ['discount_percent' => 25]), $this->manager)->status)->toBe(S::Confirmed);

        app(ResolveSettings::class)->set('sales.discount_approval_threshold_percent', 30);
        expect(app(ConfirmSalesOrder::class)(semenOrder($customer, $batch, 2, ['discount_percent' => 25]), $this->officer)->status)->toBe(S::Confirmed);
    });
});

describe('credit rules', function () {
    it('refuses an order that takes a customer past their limit (block)', function () {
        $batch = releasedSemen();
        $customer = creditCustomer(20000000);   // limit 200,000.00

        dispatched(semenOrder($customer, $batch, 10, ['discount_percent' => 10]));   // owes 135,000.00
        $next = semenOrder($customer, $batch, 10, ['discount_percent' => 10]);        // would be 270,000.00

        expect(fn () => app(ConfirmSalesOrder::class)($next, $this->manager))->toThrow(DomainException::class, 'credit limit of NGN 200,000.00');

        pay($customer, 10000000);   // owes 35,000.00; the new order brings it to 170,000.00
        expect(app(ConfirmSalesOrder::class)($next, $this->manager)->status)->toBe(S::Confirmed);
    });

    it('lets a customer without credit buy only by paying in advance', function () {
        $batch = releasedSemen();
        $cash = customer(['name' => 'Walk-in']);
        $order = semenOrder($cash, $batch, 2);   // 30,000.00

        expect(fn () => app(ConfirmSalesOrder::class)($order, $this->manager))->toThrow(DomainException::class, 'no approved credit');

        pay($cash, 3000000);   // a deposit of exactly the order
        expect(app(ConfirmSalesOrder::class)($order, $this->manager)->status)->toBe(S::Confirmed);
    });

    it('only warns under the warn rule, and keeps the warning on the order', function () {
        app(ResolveSettings::class)->set('sales.credit_enforcement', 'warn');
        $order = app(ConfirmSalesOrder::class)(semenOrder(customer(['name' => 'Walk-in']), releasedSemen(), 2), $this->manager);

        expect($order->status)->toBe(S::Confirmed)->and($order->credit_warning)->toContain('no approved credit');
    });

    it('does not check credit when the rule is off', function () {
        app(ResolveSettings::class)->set('sales.credit_enforcement', 'off');

        expect(confirmed(semenOrder(customer(['name' => 'Walk-in']), releasedSemen(), 2))->credit_warning)->toBeNull();
    });

    it('refuses a blocked customer, and treats a customer on hold as having no credit', function () {
        $batch = releasedSemen();
        $customer = creditCustomer();
        $set = app(SetCustomerCredit::class);

        $set($customer, CreditStatus::OnHold, 20000000, 30, userWithRole('General Manager'));
        expect(fn () => app(ConfirmSalesOrder::class)(semenOrder($customer, $batch, 1), $this->manager))->toThrow(DomainException::class, 'no approved credit');

        $set($customer, CreditStatus::Blocked, 20000000, 30, userWithRole('General Manager'));
        expect(fn () => app(ConfirmSalesOrder::class)(semenOrder($customer, $batch, 1), $this->manager))->toThrow(DomainException::class, 'blocked');
    });

    it('gives no new credit to a customer with overdue invoices', function () {
        $batch = releasedSemen();
        $customer = creditCustomer(100000000, 30);
        dispatched(semenOrder($customer, $batch, 2), 40);   // due 10 days ago

        expect(fn () => app(ConfirmSalesOrder::class)(semenOrder($customer, $batch, 1), $this->manager))->toThrow(DomainException::class, 'overdue');

        app(ResolveSettings::class)->set('sales.block_credit_when_overdue', false);
        expect(app(ConfirmSalesOrder::class)(semenOrder($customer, $batch, 1), $this->manager)->status)->toBe(S::Confirmed);
    });
});

describe('dispatch and invoice', function () {
    it('sends the goods out and invoices at the order prices', function () {
        $batch = releasedSemen();   // 24 doses
        $animal = register(['category_id' => categoryId('grower')]);
        $customer = creditCustomer(100000000, 30);
        $order = app(CreateSalesOrder::class)($customer, [
            ['kind' => 'semen', 'semen_batch_id' => $batch->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => 10, 'unit_price_minor' => 1500000, 'discount_percent' => 10],
            ['kind' => 'pig_animal', 'animal_id' => $animal->id, 'unit' => 'kg', 'quantity' => '110.5', 'unit_price_minor' => 120000],
        ], now());

        $invoice = app(DispatchSalesOrder::class)(confirmed($order), now()->startOfDay(), 'Truck KJA-123');

        expect($invoice->number)->toBe('INV-000001')->and($invoice->total_minor)->toBe(26760000)->and($invoice->due_on->toDateString())->toBe(now()->addDays(30)->toDateString())
            ->and($invoice->lines->pluck('line_total_minor')->all())->toBe([13500000, 13260000])
            ->and($order->fresh()->status)->toBe(S::Dispatched)->and($order->fresh()->dispatch_note)->toBe('Truck KJA-123')
            ->and($invoice->balanceMinor())->toBe(26760000)
            ->and(doses())->toBe('14.000')
            ->and($animal->fresh()->status)->toBe(AnimalStatus::Sold)
            ->and($order->lines->flatMap->reservations->every(fn ($r) => $r->fresh()->status === ReservationStatus::Fulfilled))->toBeTrue();
    });

    it('keeps semen traceable from the invoice to the batch, the boar and the ledger', function () {
        $batch = releasedSemen();
        $invoice = dispatched(semenOrder(creditCustomer(), $batch, 6));

        $line = $invoice->lines->sole();
        $sale = InventoryTransaction::where('type', T::Sale)->sole();

        expect($line->semen_batch_id)->toBe($batch->id)->and($line->semenBatch->boar->id)->toBe($batch->animal_id)
            ->and($sale->source_type)->toBe('sales_order')->and($sale->source_id)->toBe($invoice->sales_order_id)
            ->and($sale->inventory_batch_id)->toBe($batch->inventory_batch_id)->and((string) $sale->quantity)->toBe('-6.000');
    });

    it('takes sold pigs off a batch', function () {
        $batch = openBatch(['count' => 20]);
        $order = app(CreateSalesOrder::class)(creditCustomer(), [['kind' => 'pig_batch', 'production_batch_id' => $batch->id, 'heads' => 7, 'unit' => 'head', 'unit_price_minor' => 5000000]], now());

        $invoice = dispatched($order);

        expect($batch->fresh()->headCount())->toBe(13)->and($invoice->total_minor)->toBe(35000000)->and($invoice->lines->sole()->production_batch_id)->toBe($batch->id);
    });

    it('does all of it or none of it', function () {
        $batch = releasedSemen();
        $pig = register(['category_id' => categoryId('grower')]);
        $customer = creditCustomer();
        $order = confirmed(app(CreateSalesOrder::class)($customer, [
            ['kind' => 'semen', 'semen_batch_id' => $batch->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => 5, 'unit_price_minor' => 1500000],
            ['kind' => 'pig_animal', 'animal_id' => $pig->id, 'unit' => 'head', 'unit_price_minor' => 5000000],
        ], now()));

        treat($pig, medicine(30), now()->subDay()->toDateString());   // falls under withdrawal after confirmation

        expect(fn () => app(DispatchSalesOrder::class)($order, now()->startOfDay()))->toThrow(DomainException::class, 'under withdrawal');
        expect($order->fresh()->status)->toBe(S::Confirmed)->and(doses())->toBe('24.000')->and($pig->fresh()->status)->toBe(AnimalStatus::Active)
            ->and(Invoice::count())->toBe(0)->and(InventoryTransaction::where('type', T::Sale)->count())->toBe(0);
    });

    it('re-checks that the semen can still be sold', function () {
        $batch = releasedSemen();
        $order = confirmed(semenOrder(creditCustomer(), $batch, 4));
        app(ManageSemenBatch::class)->quarantine($batch, 'Recall');

        expect(fn () => app(DispatchSalesOrder::class)($order, now()->startOfDay()))->toThrow(DomainException::class, 'cannot be sold or used');
        expect(Invoice::count())->toBe(0);
    });

    it('only dispatches a confirmed order, once, with a sensible date', function () {
        $order = semenOrder(creditCustomer(), releasedSemen(), 2);

        expect(fn () => app(DispatchSalesOrder::class)($order, now()))->toThrow(DomainException::class, 'only a confirmed order');
        confirmed($order);
        expect(fn () => app(DispatchSalesOrder::class)($order->fresh(), now()->addDay()))->toThrow(DomainException::class, 'cannot be in the future');
        expect(fn () => app(DispatchSalesOrder::class)($order->fresh(), now()->subDays(5)))->toThrow(DomainException::class, 'before the order date');

        app(DispatchSalesOrder::class)($order->fresh(), now()->startOfDay());
        expect(fn () => app(DispatchSalesOrder::class)($order->fresh(), now()))->toThrow(DomainException::class, 'only a confirmed order');
    });

    it('never changes an invoice when prices change later', function () {
        $batch = releasedSemen();
        $invoice = dispatched(semenOrder(creditCustomer(), $batch, 2));
        $list = PriceList::create(['farm_id' => Farm::first()->id, 'category_id' => lookup(LookupCategory::PriceCategory, 'semen'), 'code' => 'NEW', 'name' => 'New', 'currency_code' => 'NGN', 'is_active' => true]);
        PriceListItem::create(['price_list_id' => $list->id, 'code' => 'DUR', 'description' => 'Duroc', 'unit_id' => UnitOfMeasure::firstWhere('code', 'DOSE')->id, 'unit_price_minor' => 9900000, 'inventory_item_id' => InventoryItem::firstWhere('code', 'SEMEN-DUR')->id]);

        expect($invoice->fresh()->total_minor)->toBe(3000000)->and($invoice->lines->sole()->fresh()->unit_price_minor)->toBe(1500000);
        expect(fn () => $invoice->lines->sole()->update(['unit_price_minor' => 1]))->toThrow(LogicException::class);
        expect(fn () => $invoice->update(['total_minor' => 1]))->toThrow(LogicException::class);
    });
});

describe('payments and balances', function () {
    it('settles invoices oldest first and records part payments', function () {
        $batch = releasedSemen();
        $customer = creditCustomer(100000000);
        $old = dispatched(semenOrder($customer, $batch, 4), 10);   // 60,000.00
        $new = dispatched(semenOrder($customer, $batch, 2), 0);    // 30,000.00

        $payment = pay($customer, 7000000);   // 70,000.00

        expect($payment->number)->toBe('RCT-000001')->and($old->fresh()->balanceMinor())->toBe(0)->and($new->fresh()->balanceMinor())->toBe(2000000)
            ->and(app(GetCustomerAccount::class)($customer)['outstanding'])->toBe(2000000);

        pay($customer, 500000);
        expect($new->fresh()->balanceMinor())->toBe(1500000);
    });

    it('puts a payment against the invoices named', function () {
        $batch = releasedSemen();
        $customer = creditCustomer(100000000);
        $a = dispatched(semenOrder($customer, $batch, 2), 5);
        $b = dispatched(semenOrder($customer, $batch, 2), 0);

        pay($customer, 2000000, [$b->id => 2000000]);

        expect($a->fresh()->balanceMinor())->toBe(3000000)->and($b->fresh()->balanceMinor())->toBe(1000000);
        expect(fn () => pay($customer, 100, [$b->id => 2000000]))->toThrow(DomainException::class, 'more than the');
        expect(fn () => pay($customer, 100, [$b->id => 100, $a->id => 100]))->toThrow(DomainException::class, 'more than was received');
        expect(fn () => pay($customer, 100, [999 => 100]))->toThrow(DomainException::class, 'not this customer');
        expect(fn () => pay(creditCustomer(over: ['name' => 'Other']), 100, [$a->id => 100]))->toThrow(DomainException::class, 'not this customer');
    });

    it('keeps money not allocated as a deposit and uses it on the next invoice', function () {
        $batch = releasedSemen();
        $customer = creditCustomer(100000000);

        pay($customer, 5000000);   // nothing owed yet: all deposit
        expect(app(GetCustomerAccount::class)($customer)['deposit'])->toBe(5000000);

        $invoice = dispatched(semenOrder($customer, $batch, 2));   // 30,000.00: paid from the deposit at dispatch

        expect($invoice->fresh()->balanceMinor())->toBe(0)->and(app(GetCustomerAccount::class)($customer)['deposit'])->toBe(2000000);
        expect(app(AllocateCustomerFunds::class)($invoice->fresh()))->toBe(0);
    });

    it('applies a deposit by hand, never beyond what the invoice needs', function () {
        $customer = creditCustomer(100000000);
        $invoice = dispatched(semenOrder($customer, releasedSemen(), 2));   // 30,000.00 owed
        pay($customer, 1000000, []);                                          // 10,000.00 deposit

        expect(app(AllocateCustomerFunds::class)($invoice, 400000))->toBe(400000)->and($invoice->fresh()->balanceMinor())->toBe(2600000);
        expect(app(AllocateCustomerFunds::class)($invoice))->toBe(600000)->and(app(GetCustomerAccount::class)($customer)['deposit'])->toBe(0);
    });

    it('can void a payment, which makes the invoice owe again', function () {
        $customer = creditCustomer(100000000);
        $invoice = dispatched(semenOrder($customer, releasedSemen(), 2));
        $payment = pay($customer, 3000000);

        expect($invoice->fresh()->balanceMinor())->toBe(0);
        app(VoidCustomerPayment::class)($payment, 'Cheque bounced');

        expect($invoice->fresh()->balanceMinor())->toBe(3000000)->and($payment->fresh()->isVoided())->toBeTrue()->and(app(GetCustomerAccount::class)($customer)['deposit'])->toBe(0);
        expect(fn () => app(VoidCustomerPayment::class)($payment, 'Again'))->toThrow(DomainException::class, 'already voided');
        expect(fn () => app(VoidCustomerPayment::class)(pay($customer, 100), ' '))->toThrow(DomainException::class, 'reason');
        expect(fn () => $payment->update(['amount_minor' => 1]))->toThrow(LogicException::class);
    });

    it('checks a payment and can be repeated safely', function () {
        $customer = customer();

        expect(fn () => pay($customer, 0))->toThrow(DomainException::class, 'positive');
        expect(fn () => app(RecordCustomerPayment::class)($customer, 100, ReceiptMethod::Cash, now()->addDay()))->toThrow(DomainException::class, 'future');

        $a = app(RecordCustomerPayment::class)($customer, 500, ReceiptMethod::Pos, now(), null, null, null, null, 'k1');
        $b = app(RecordCustomerPayment::class)($customer, 500, ReceiptMethod::Pos, now(), null, null, null, null, 'k1');
        expect($b->id)->toBe($a->id);
        expect(fn () => app(RecordCustomerPayment::class)($customer, 999, ReceiptMethod::Pos, now(), null, null, null, null, 'k1'))->toThrow(DomainException::class, 'idempotency');
    });

    it('lists a customer\'s history and everyone who owes money', function () {
        $batch = releasedSemen();
        $debtor = creditCustomer(100000000, 30, ['name' => 'Debtor']);
        $prepaid = customer(['name' => 'Prepaid']);
        $invoice = dispatched(semenOrder($debtor, $batch, 2), 3);
        pay($debtor, 1000000);
        pay($prepaid, 700000);
        customer(['name' => 'Quiet']);

        $history = app(GetCustomerHistory::class)($debtor);
        $balances = app(GetOutstandingBalances::class)();

        expect($history->pluck('type')->sort()->values()->all())->toBe(['Invoice', 'Order', 'Payment'])
            ->and($history->firstWhere('type', 'Invoice')['number'])->toBe($invoice->number)
            ->and($balances->pluck('customer.name')->all())->toBe(['Debtor', 'Prepaid'])
            ->and($balances->first())->toMatchArray(['outstanding' => 2000000, 'deposit' => 0])
            ->and($balances->last())->toMatchArray(['outstanding' => 0, 'deposit' => 700000]);
    });
});

it('grants sales rights by role', function () {
    expect($this->officer->can('sales.create'))->toBeTrue()->and($this->officer->can('sales.approve'))->toBeFalse()
        ->and($this->manager->can('sales.approve'))->toBeTrue()
        ->and(userWithRole('Accountant')->can('sales.create'))->toBeTrue()->and(userWithRole('Accountant')->can('sales.approve'))->toBeFalse()
        ->and(farmWorker()->can('sales.view'))->toBeFalse()
        ->and(owner()->can('delete', semenOrder(customer(), releasedSemen(), 1)))->toBeFalse()
        ->and(owner()->can('delete', customer(['name' => 'Fresh'])))->toBeTrue();
});
