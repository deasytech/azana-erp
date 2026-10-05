<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Procurement\Actions\DecidePurchaseOrder;
use App\Domain\Procurement\Actions\RecordSupplierInvoice;
use App\Domain\Procurement\Actions\RecordSupplierPayment;
use App\Domain\Procurement\Models\GoodsReceipt;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseRequest;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Procurement\Models\SupplierPayment;
use App\Domain\Supplier\Models\Supplier;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseOrderStatus as PO;
use App\Enums\PurchaseRequestStatus as PR;
use App\Filament\Pages\SupplierBalances;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder as CreateOrderPage;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrders\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\PurchaseOrders\RelationManagers\ReceiptsRelationManager;
use App\Filament\Resources\PurchaseRequests\Pages\CreatePurchaseRequest as CreateRequestPage;
use App\Filament\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Resources\SupplierInvoices\Pages\ListSupplierInvoices;
use App\Filament\Resources\SupplierInvoices\SupplierInvoiceResource;
use App\Filament\Resources\SupplierPayments\Pages\ListSupplierPayments;
use App\Filament\Resources\SupplierPayments\SupplierPaymentResource;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\SupplierResource;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('renders every purchasing page for the owner', function () {
    $this->actingAs(owner());
    $order = purchaseOrder();
    receiveGoods($order, ['40']);
    app(RecordSupplierInvoice::class)($order, 'INV-1', now(), 1000000);
    $request = approvedRequest(userWithRole('Store Officer'), userWithRole('Farm Manager'));

    foreach ([
        SupplierBalances::getUrl(),
        SupplierResource::getUrl('index'), SupplierResource::getUrl('create'),
        PurchaseRequestResource::getUrl('index'), PurchaseRequestResource::getUrl('create'), PurchaseRequestResource::getUrl('view', ['record' => $request]),
        PurchaseOrderResource::getUrl('index'), PurchaseOrderResource::getUrl('create'), PurchaseOrderResource::getUrl('view', ['record' => $order]),
        GoodsReceiptResource::getUrl('index'), SupplierInvoiceResource::getUrl('index'), SupplierPaymentResource::getUrl('index'),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(PurchaseOrderResource::getUrl('view', ['record' => $order]))->assertSee($order->number)->assertSee('Received so far');
});

it('creates a supplier with payment terms', function () {
    $this->actingAs(owner());

    Livewire::test(CreateSupplier::class)
        ->fillForm(['code' => 'agro-1', 'name' => 'Agro Feeds Ltd', 'payment_terms_days' => 21])
        ->call('create')->assertHasNoFormErrors();

    expect(Supplier::firstWhere('code', 'AGRO-1')->payment_terms_days)->toBe(21);
});

it('takes a request from draft to an order through the screens', function () {
    $clerk = userWithRole('Store Officer');
    $manager = userWithRole('Farm Manager');
    $maize = stockItem('MAIZE');

    $this->actingAs($clerk);
    Livewire::test(CreateRequestPage::class)
        ->fillForm(['lines' => [['inventory_item_id' => $maize->id, 'quantity' => '100', 'estimated_unit_cost_minor' => '350.00']]])
        ->call('create')->assertHasNoFormErrors();
    $request = PurchaseRequest::sole();
    expect($request->lines->sole()->estimated_unit_cost_minor)->toBe(35000);

    Livewire::test(ViewPurchaseRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionHidden('approve')->callAction('submit')->assertNotified('Request submitted')->assertActionHidden('approve');

    $this->actingAs($manager);
    Livewire::test(ViewPurchaseRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionVisible('approve')->callAction('approve')->assertNotified('Request approved')->assertActionVisible('order');
    expect($request->fresh()->status)->toBe(PR::Approved);

    // "Create purchase order" opens the order form with the request's items filled in.
    $supplier = supplier();
    Livewire::withQueryParams(['request' => $request->id])->test(CreateOrderPage::class)
        ->assertFormSet(['purchase_request_id' => $request->id])
        ->fillForm(['supplier_id' => $supplier->id])
        ->call('create')->assertHasNoFormErrors();

    $order = PurchaseOrder::sole();
    expect($order->total_minor)->toBe(3500000)->and($order->purchase_request_id)->toBe($request->id)->and($request->fresh()->status)->toBe(PR::Ordered);
});

it('reports a rule violation when creating an order instead of saving it', function () {
    $this->actingAs(owner());

    Livewire::test(CreateOrderPage::class)
        ->fillForm(['supplier_id' => supplier()->id, 'ordered_on' => now()->toDateString(), 'expected_on' => now()->subDays(3)->toDateString(),
            'lines' => [['inventory_item_id' => stockItem()->id, 'quantity' => '1', 'unit_cost_minor' => '1.00']]])
        ->call('create')->assertNotified('Not saved');

    expect(PurchaseOrder::count())->toBe(0);
});

it('approves an order, receives goods, invoices and pays it from the order page', function () {
    $manager = userWithRole('Farm Manager');
    $order = purchaseOrder(approve: false);
    $this->actingAs($manager);

    $page = fn () => Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()]);

    $page()->assertActionVisible('submit')->assertActionHidden('receive')->callAction('submit')->assertNotified('Order submitted');
    expect($order->fresh()->status)->toBe(PO::PendingApproval);
    $page()->assertActionVisible('approve')->callAction('approve')->assertNotified('Order approved');

    $line = $order->lines()->sole();
    $page()->assertActionVisible('receive')
        ->callAction('receive', ['inventory_location_id' => store()->id, 'received_on' => now()->toDateString(), 'delivery_note' => 'DN-7',
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => '60']]])
        ->assertNotified('Goods received');

    expect($order->fresh()->status)->toBe(PO::PartiallyReceived)->and(app(GetStockLevels::class)->total(stockItem()->id))->toBe('60.000');

    $page()->assertActionVisible('invoice')
        ->callAction('invoice', ['invoice_number' => 'INV-9', 'invoice_date' => now()->toDateString(), 'subtotal_minor' => '21000.00', 'tax_minor' => '1575.00'])
        ->assertNotified('Invoice recorded');
    $invoice = SupplierInvoice::sole();
    expect($invoice->total_minor)->toBe(2257500);

    Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewPurchaseOrder::class])
        ->callAction(TestAction::make('pay')->table($invoice), ['amount_minor' => '22575.00', 'paid_on' => now()->toDateString(), 'method' => 'bank_transfer', 'reference' => 'TRF-1'])
        ->assertNotified('Payment recorded');

    expect($invoice->fresh()->balanceMinor())->toBe(0)->and(SupplierPayment::sole()->status)->toBe(PaymentStatus::Paid);
});

it('hides the approval buttons from people who may not approve', function () {
    $order = purchaseOrder(approve: false);
    app(DecidePurchaseOrder::class)->submit($order);

    $this->actingAs(userWithRole('Store Officer'));
    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()])->assertActionHidden('approve')->assertActionHidden('reject');
});

it('shows a rule violation from receiving goods as a notification', function () {
    $this->actingAs(owner());
    $order = purchaseOrder();
    $line = $order->lines()->sole();

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('receive', ['inventory_location_id' => store()->id, 'received_on' => now()->toDateString(), 'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => '500']]])
        ->assertNotified('Not saved');

    expect(GoodsReceipt::count())->toBe(0);
});

it('asks for batch details when the delivered item is batch tracked', function () {
    $this->actingAs(owner());
    $vaccine = stockItem('VAC', ['tracks_batches' => true, 'tracks_expiry' => true]);
    $order = purchaseOrder([['inventory_item_id' => $vaccine->id, 'quantity' => '10', 'unit_cost_minor' => 5000]]);
    $line = $order->lines()->sole();
    $base = ['inventory_location_id' => store()->id, 'received_on' => now()->toDateString()];

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('receive', $base + ['lines' => [['purchase_order_line_id' => $line->id, 'quantity' => '10', 'tracks_batches' => true, 'tracks_expiry' => true]]])
        ->assertHasActionErrors();

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('receive', $base + ['lines' => [['purchase_order_line_id' => $line->id, 'quantity' => '10', 'tracks_batches' => true, 'tracks_expiry' => true,
            'batch_number' => 'LOT-1', 'expiry_date' => now()->addYear()->toDateString()]]])
        ->assertNotified('Goods received');

    expect(InventoryBatch::sole()->supplier_id)->toBe($order->supplier_id);
});

it('voids a receipt from the order page and from the receipts list', function () {
    $this->actingAs(owner());
    $order = purchaseOrder();
    $receipt = receiveGoods($order, ['100']);

    Livewire::test(ReceiptsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewPurchaseOrder::class])
        ->callAction(TestAction::make('void')->table($receipt), ['reason' => 'Wrong delivery']);

    expect($receipt->fresh()->isVoided())->toBeTrue()->and($order->fresh()->status)->toBe(PO::Approved);

    Livewire::test(ListGoodsReceipts::class)->assertCanSeeTableRecords([$receipt])->assertActionHidden(TestAction::make('void')->table($receipt));
});

it('holds a large payment for approval and lets another user approve it', function () {
    app(ResolveSettings::class)->set('procurement.payment_approval_threshold_minor', 1000000);
    $clerk = userWithRole('Accountant');
    $manager = userWithRole('Farm Manager');
    $order = purchaseOrder();
    receiveGoods($order, ['100']);
    $invoice = app(RecordSupplierInvoice::class)($order, 'INV-1', now(), 3500000);

    $this->actingAs($clerk);
    Livewire::test(ListSupplierInvoices::class)
        ->callAction(TestAction::make('pay')->table($invoice), ['amount_minor' => '35000.00', 'paid_on' => now()->toDateString(), 'method' => 'cheque'])
        ->assertNotified('Payment held for approval');
    $payment = SupplierPayment::sole();

    Livewire::test(ListSupplierPayments::class)->assertActionHidden(TestAction::make('approve')->table($payment));

    $this->actingAs($manager);
    Livewire::test(ListSupplierPayments::class)
        ->callAction(TestAction::make('approve')->table($payment), ['notes' => 'ok'])->assertNotified('Payment approved');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)->and($invoice->fresh()->balanceMinor())->toBe(0);

    Livewire::test(ListSupplierPayments::class)->callAction(TestAction::make('void')->table($payment->fresh()), ['reason' => 'Bounced'])->assertNotified('Payment voided');
    expect($invoice->fresh()->balanceMinor())->toBe(3500000);
});

it('shows what each supplier is owed', function () {
    $this->actingAs(owner());
    $order = purchaseOrder();
    receiveGoods($order, ['100']);
    $invoice = app(RecordSupplierInvoice::class)($order, 'INV-1', now()->subDays(60), 3500000);
    app(RecordSupplierPayment::class)($invoice, 500000, now(), PaymentMethod::Cash);

    Livewire::test(SupplierBalances::class)->assertSee('Supplier SUP1')->assertSee('NGN 30,000.00')->assertSee('NGN 5,000.00');
});

it('keeps purchasing screens away from people without purchasing rights', function () {
    $this->actingAs(farmWorker());

    foreach ([PurchaseOrderResource::getUrl('index'), PurchaseRequestResource::getUrl('index'), SupplierInvoiceResource::getUrl('index'), SupplierBalances::getUrl()] as $url) {
        $this->get($url)->assertForbidden();
    }
});
