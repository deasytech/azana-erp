<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\CreditStatus;
use App\Enums\LookupCategory;
use App\Enums\SalesOrderStatus as S;
use App\Filament\Pages\OutstandingBalances;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\CustomerPayments\Pages\CreateCustomerPayment;
use App\Filament\Resources\CustomerPayments\Pages\ListCustomerPayments;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\LinesRelationManager as InvoiceLines;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function salesOrderPage(SalesOrder $order)
{
    return Livewire::test(ViewSalesOrder::class, ['record' => $order->getRouteKey()]);
}

it('renders every sales page for the owner', function () {
    $this->actingAs(owner());
    $customer = creditCustomer();
    $invoice = dispatched(semenOrder($customer, releasedSemen(), 3));
    pay($customer, 1000000);

    foreach ([
        OutstandingBalances::getUrl(),
        CustomerResource::getUrl('index'), CustomerResource::getUrl('create'), CustomerResource::getUrl('view', ['record' => $customer]), CustomerResource::getUrl('edit', ['record' => $customer]),
        SalesOrderResource::getUrl('index'), SalesOrderResource::getUrl('create'), SalesOrderResource::getUrl('view', ['record' => $invoice->order]),
        InvoiceResource::getUrl('index'), InvoiceResource::getUrl('view', ['record' => $invoice]),
        CustomerPaymentResource::getUrl('index'), CustomerPaymentResource::getUrl('create'),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(CustomerResource::getUrl('view', ['record' => $customer]))->assertSee('Green Acres Farm')->assertSee('NGN 35,000.00');   // 45,000.00 invoiced, 10,000.00 paid
});

it('creates and edits a customer', function () {
    $this->actingAs(owner());
    $type = lookup(LookupCategory::CustomerType, 'butcher');

    Livewire::test(CreateCustomer::class)->fillForm(['name' => 'City Meats', 'customer_type_id' => $type, 'phone' => '0801', 'email' => 'x@y.test'])->call('create')->assertHasNoFormErrors();

    $customer = Customer::sole();
    expect($customer->code)->toBe('C-000001')->and($customer->credit_status)->toBe(CreditStatus::None);

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])->fillForm(['name' => 'City Meats Ltd'])->call('save')->assertHasNoFormErrors();
    expect($customer->fresh()->name)->toBe('City Meats Ltd');

    Livewire::test(CreateCustomer::class)->fillForm(['name' => 'Bad', 'customer_type_id' => $type, 'email' => 'nope'])->call('create')->assertHasFormErrors(['email']);
});

it('lets only an approver set credit, and shows the account', function () {
    $customer = customer(['name' => 'Credit Co']);

    $this->actingAs($this->officer = userWithRole('Sales Officer'));
    Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])->assertActionHidden('credit')->assertActionVisible('receive_payment');

    $this->actingAs(userWithRole('Farm Manager'));
    Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])->assertActionVisible('credit')
        ->callAction('credit', ['status' => 'approved', 'limit' => '150000.00', 'terms' => 45])->assertNotified('Credit updated')
        ->assertSee('NGN 150,000.00')->assertSee('45 days');

    // The form opens showing the limit already set, in money (not minor units).
    Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])->mountAction('credit')->assertActionDataSet(['limit' => '150000.00', 'terms' => 45, 'status' => 'approved']);

    expect($customer->fresh())->credit_limit_minor->toBe(15000000)->payment_terms_days->toBe(45)->credit_status->toBe(CreditStatus::Approved);
});

it('drafts an order of semen and a pig through the form', function () {
    $this->actingAs(owner());
    $batch = releasedSemen();
    $pig = register(['category_id' => categoryId('grower')]);
    $customer = creditCustomer();

    Livewire::test(CreateSalesOrder::class)
        ->fillForm(['customer_id' => $customer->id, 'ordered_on' => now()->toDateString(), 'lines' => [
            ['kind' => 'semen', 'semen_batch_id' => $batch->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => 10, 'unit_price_minor' => '15000.00', 'discount_percent' => 10],
            ['kind' => 'pig_animal', 'animal_id' => $pig->id, 'unit' => 'kg', 'quantity' => 110.5, 'unit_price_minor' => '1200.00', 'discount_percent' => 0],
        ]])
        ->call('create')->assertHasNoFormErrors();

    $order = SalesOrder::sole();
    expect($order->total_minor)->toBe(26760000)->and($order->status)->toBe(S::Draft)->and($order->lines)->toHaveCount(2);

    // A rule violation (a price is missing for a pig) is a notification, not an error page.
    Livewire::test(CreateSalesOrder::class)
        ->fillForm(['customer_id' => $customer->id, 'ordered_on' => now()->toDateString(), 'lines' => [['kind' => 'pig_animal', 'animal_id' => $pig->id, 'unit' => 'head']]])
        ->call('create')->assertNotified('Not saved');
    expect(SalesOrder::count())->toBe(1);
});

it('confirms, dispatches and invoices an order from its page', function () {
    $this->actingAs(userWithRole('Farm Manager'));
    $batch = releasedSemen();
    $order = semenOrder(creditCustomer(), $batch, 10, ['discount_percent' => 10]);

    salesOrderPage($order)->assertActionVisible('confirm')->assertActionHidden('dispatch')
        ->callAction('confirm')->assertNotified('Order confirmed and stock reserved')
        ->assertActionHidden('confirm')->assertActionVisible('dispatch');
    expect($order->fresh()->status)->toBe(S::Confirmed)->and(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'SEMEN-DUR')->id))->toBe('24.000');

    salesOrderPage($order)->callAction('dispatch', ['dispatched_on' => now()->toDateString(), 'dispatch_note' => 'Truck 7'])->assertNotified('Order dispatched and invoiced')
        ->assertSee('INV-000001')->assertSee('Truck 7')->assertActionHidden('dispatch')->assertActionHidden('cancel');

    expect($order->fresh()->status)->toBe(S::Dispatched)->and(Invoice::sole()->total_minor)->toBe(13500000)
        ->and(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'SEMEN-DUR')->id))->toBe('14.000');
});

it('shows a credit refusal as a notification and confirms nothing', function () {
    $this->actingAs(userWithRole('Farm Manager'));
    $order = semenOrder(customer(['name' => 'Walk-in']), releasedSemen(), 2);

    salesOrderPage($order)->callAction('confirm')->assertNotified('Not saved');

    expect($order->fresh()->status)->toBe(S::Draft);
});

it('shows a credit warning on the order under the warn rule', function () {
    $this->actingAs(userWithRole('Farm Manager'));
    app(ResolveSettings::class)->set('sales.credit_enforcement', 'warn');
    $order = semenOrder(customer(['name' => 'Walk-in']), releasedSemen(), 2);

    salesOrderPage($order)->callAction('confirm')->assertNotified('Order confirmed and stock reserved')->assertSee('no approved credit');
});

it('cancels an order and shows why', function () {
    $this->actingAs(userWithRole('Farm Manager'));
    $order = confirmed(semenOrder(creditCustomer(), releasedSemen(), 3));

    salesOrderPage($order)->callAction('cancel', ['reason' => 'Customer withdrew'])->assertNotified('Order cancelled')->assertSee('Customer withdrew');

    expect($order->fresh()->status)->toBe(S::Cancelled);
});

it('asks a salesperson for an approver when a discount is above the limit', function () {
    $this->actingAs(userWithRole('Sales Officer'));
    $order = semenOrder(creditCustomer(), releasedSemen(), 2, ['discount_percent' => 20]);

    salesOrderPage($order)->callAction('confirm')->assertNotified('Not saved');
    expect($order->fresh()->status)->toBe(S::Draft);

    $this->actingAs(userWithRole('Farm Manager'));
    salesOrderPage($order)->callAction('confirm')->assertNotified('Order confirmed and stock reserved');
});

it('shows the invoice with its lines traced to the semen batch', function () {
    $this->actingAs(owner());
    $batch = releasedSemen();
    $invoice = dispatched(semenOrder(creditCustomer(), $batch, 4));

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])->assertSee('INV-000001')->assertSee('NGN 60,000.00')->assertSee($invoice->order->number);
    Livewire::test(InvoiceLines::class, ['ownerRecord' => $invoice, 'pageClass' => ViewInvoice::class])->assertSee($batch->number)->assertSee($batch->boar->animal_number);
});

it('receives a payment oldest invoice first, by choice, or as a deposit', function () {
    $this->actingAs(owner());
    $customer = creditCustomer();
    $old = dispatched(semenOrder($customer, releasedSemen(), 4), 5);   // 60,000.00
    $new = dispatched(semenOrder($customer, SemenBatch::first(), 2), 0);   // 30,000.00

    Livewire::test(CreateCustomerPayment::class)->fillForm(['customer_id' => $customer->id, 'amount_minor' => '65000.00', 'method' => 'cash', 'received_on' => now()->toDateString(), 'apply' => 'oldest'])
        ->call('create')->assertHasNoFormErrors();
    expect($old->fresh()->balanceMinor())->toBe(0)->and($new->fresh()->balanceMinor())->toBe(2500000);

    Livewire::test(CreateCustomerPayment::class)->fillForm(['customer_id' => $customer->id, 'amount_minor' => '10000.00', 'method' => 'pos', 'received_on' => now()->toDateString(), 'apply' => 'invoices',
        'invoices' => [['invoice_id' => $new->id, 'amount_minor' => '10000.00']]])->call('create')->assertHasNoFormErrors();
    expect($new->fresh()->balanceMinor())->toBe(1500000);

    Livewire::test(CreateCustomerPayment::class)->fillForm(['customer_id' => $customer->id, 'amount_minor' => '3000.00', 'method' => 'bank_transfer', 'received_on' => now()->toDateString(), 'apply' => 'deposit'])
        ->call('create')->assertHasNoFormErrors();
    expect($new->fresh()->balanceMinor())->toBe(1500000)->and(Payment::count())->toBe(3);

    Livewire::test(CreateCustomerPayment::class)->fillForm(['customer_id' => $customer->id, 'amount_minor' => '5.00', 'method' => 'cash', 'received_on' => now()->toDateString(), 'apply' => 'invoices',
        'invoices' => [['invoice_id' => $new->id, 'amount_minor' => '9999.00']]])->call('create')->assertNotified('Not saved');
});

it('opens the payment form for a customer or an invoice, with its balance ready', function () {
    $this->actingAs(owner());
    $customer = creditCustomer();
    $invoice = dispatched(semenOrder($customer, releasedSemen(), 2));

    Livewire::withQueryParams(['customer' => $customer->id, 'invoice' => $invoice->id])->test(CreateCustomerPayment::class)
        ->assertFormSet(['customer_id' => $customer->id, 'apply' => 'invoices', 'amount_minor' => '30000.00', 'method' => 'bank_transfer']);

    Livewire::withQueryParams(['customer' => $customer->id])->test(CreateCustomerPayment::class)->assertFormSet(['customer_id' => $customer->id, 'apply' => 'oldest']);
    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])->assertActionVisible('receive_payment');
});

it('voids a payment from the list', function () {
    $this->actingAs(owner());
    $customer = creditCustomer();
    $invoice = dispatched(semenOrder($customer, releasedSemen(), 2));
    $payment = pay($customer, 3000000);

    Livewire::test(ListCustomerPayments::class)->assertCanSeeTableRecords([$payment])
        ->callAction(TestAction::make('void')->table($payment), ['reason' => 'Cheque bounced'])->assertNotified('Payment voided');

    expect($invoice->fresh()->balanceMinor())->toBe(3000000);
    Livewire::test(ListCustomerPayments::class)->assertActionHidden(TestAction::make('void')->table($payment));
});

it('lists who owes money, and a customer\'s orders on their page', function () {
    $this->actingAs(owner());
    $debtor = creditCustomer(1000000000, 30, ['name' => 'Debtor Co']);
    $batch = releasedSemen();
    $order = semenOrder($debtor, $batch, 2);
    dispatched($order);
    customer(['name' => 'Quiet Co']);

    Livewire::test(OutstandingBalances::class)->assertSee('Debtor Co')->assertDontSee('Quiet Co')->assertSee('NGN 30,000.00');
    Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $debtor, 'pageClass' => ViewCustomer::class])->assertSee($order->number);
});

it('keeps the sales screens away from people without sales rights', function () {
    $this->actingAs(farmWorker());

    foreach ([CustomerResource::getUrl('index'), SalesOrderResource::getUrl('index'), InvoiceResource::getUrl('index'), CustomerPaymentResource::getUrl('index'), OutstandingBalances::getUrl()] as $url) {
        $this->get($url)->assertForbidden();
    }

    $this->actingAs(userWithRole('Store Officer'));
    $this->get(InvoiceResource::getUrl('index'))->assertOk();
    $this->get(SalesOrderResource::getUrl('create'))->assertForbidden();
});
