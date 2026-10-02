<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Procurement\Actions\CreatePurchaseOrder;
use App\Domain\Procurement\Actions\CreatePurchaseRequest;
use App\Domain\Procurement\Actions\DecidePurchaseOrder;
use App\Domain\Procurement\Actions\DecidePurchaseRequest;
use App\Domain\Procurement\Actions\DecideSupplierPayment;
use App\Domain\Procurement\Actions\GetPurchaseTrace;
use App\Domain\Procurement\Actions\GetSupplierBalances;
use App\Domain\Procurement\Actions\RecordSupplierInvoice;
use App\Domain\Procurement\Actions\RecordSupplierPayment;
use App\Domain\Procurement\Actions\VoidGoodsReceipt;
use App\Domain\Procurement\Actions\VoidSupplierInvoice;
use App\Domain\Procurement\Models\GoodsReceipt;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseOrderStatus as PO;
use App\Enums\PurchaseRequestStatus as PR;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    $this->clerk = userWithRole('Store Officer');
    $this->manager = userWithRole('Farm Manager');
});

function approvedRequest($clerk, $manager)
{
    $decide = app(DecidePurchaseRequest::class);
    $request = app(CreatePurchaseRequest::class)([
        ['inventory_item_id' => stockItem()->id, 'quantity' => '100', 'estimated_unit_cost_minor' => 35000],
    ], now()->addDays(7), 'Running low', $clerk);
    $decide->submit($request);

    return $decide->approve($request, $manager);
}

describe('purchase requests', function () {
    it('goes from draft to approved only through someone else with approval rights', function () {
        $request = app(CreatePurchaseRequest::class)([['inventory_item_id' => stockItem()->id, 'quantity' => '100']], null, null, $this->clerk);
        $decide = app(DecidePurchaseRequest::class);

        expect($request->number)->toStartWith('PR-')->and($request->status)->toBe(PR::Draft);
        expect(fn () => $decide->approve($request, $this->manager))->toThrow(DomainException::class, 'submitted');

        $decide->submit($request);
        expect(fn () => $decide->approve($request, $this->clerk))->toThrow(DomainException::class, 'not authorised');
        expect(fn () => $decide->approve($request, farmWorker()))->toThrow(DomainException::class, 'not authorised');

        $approved = $decide->approve($request, $this->manager, 'Go ahead');
        expect($approved->status)->toBe(PR::Approved)->and($approved->decided_by)->toBe($this->manager->id);
    });

    it('lets the requester approve their own request only when separate approval is off', function () {
        $request = app(CreatePurchaseRequest::class)([['inventory_item_id' => stockItem()->id, 'quantity' => '1']], null, null, $this->manager);
        app(DecidePurchaseRequest::class)->submit($request);

        expect(fn () => app(DecidePurchaseRequest::class)->approve($request, $this->manager))->toThrow(DomainException::class, 'someone other than');

        app(ResolveSettings::class)->set('procurement.require_separate_approver', false);
        expect(app(DecidePurchaseRequest::class)->approve($request, $this->manager)->status)->toBe(PR::Approved);
    });

    it('checks its lines and can be rejected or cancelled', function () {
        $create = app(CreatePurchaseRequest::class);
        $item = stockItem()->id;

        expect(fn () => $create([]))->toThrow(DomainException::class, 'at least one item');
        expect(fn () => $create([['inventory_item_id' => $item, 'quantity' => '0']]))->toThrow(DomainException::class, 'positive');
        expect(fn () => $create([['inventory_item_id' => $item, 'quantity' => '1'], ['inventory_item_id' => $item, 'quantity' => '2']]))->toThrow(DomainException::class, 'only once');
        expect(fn () => $create([['inventory_item_id' => 9999, 'quantity' => '1']]))->toThrow(DomainException::class, 'active stock item');

        $request = $create([['inventory_item_id' => $item, 'quantity' => '5']]);
        app(DecidePurchaseRequest::class)->submit($request);
        expect(fn () => app(DecidePurchaseRequest::class)->reject($request, $this->manager, ' '))->toThrow(DomainException::class, 'reason');
        expect(app(DecidePurchaseRequest::class)->reject($request, $this->manager, 'Not needed')->status)->toBe(PR::Rejected);
        expect(fn () => app(DecidePurchaseRequest::class)->cancel($request))->toThrow(DomainException::class);

        $other = $create([['inventory_item_id' => $item, 'quantity' => '5']]);
        expect(app(DecidePurchaseRequest::class)->cancel($other)->status)->toBe(PR::Cancelled);
    });
});

describe('purchase orders', function () {
    it('totals its lines in minor units and keeps the supplier terms it was made with', function () {
        $supplier = supplier('SUP1', 14);
        $order = app(CreatePurchaseOrder::class)($supplier, [
            ['inventory_item_id' => stockItem('MAIZE')->id, 'quantity' => '100.5', 'unit_cost_minor' => 35050],
            ['inventory_item_id' => stockItem('SOYA')->id, 'quantity' => '10', 'unit_cost_minor' => 90000],
        ], now()->startOfDay());

        $supplier->update(['payment_terms_days' => 60]);

        expect($order->number)->toStartWith('PO-')->and($order->status)->toBe(PO::Draft)
            ->and($order->lines->pluck('line_total_minor')->all())->toBe([3522525, 900000])
            ->and($order->total_minor)->toBe(4422525)
            ->and($order->payment_terms_days)->toBe(14)
            ->and($order->currency_code)->toBe('NGN');
    });

    it('validates the supplier, lines and dates', function () {
        $create = app(CreatePurchaseOrder::class);
        $line = ['inventory_item_id' => stockItem()->id, 'quantity' => '1', 'unit_cost_minor' => 100];

        expect(fn () => $create(supplier(), [['inventory_item_id' => stockItem()->id, 'quantity' => '1']], now()))->toThrow(DomainException::class, 'unit cost');
        foreach (['12.5', '1e3', -1, '-5', 1.5, true, 'abc'] as $bad) {
            expect(fn () => $create(supplier(), [['unit_cost_minor' => $bad] + $line], now()))->toThrow(DomainException::class, 'unit cost');
        }
        expect(fn () => $create(supplier(), [['unit_cost_minor' => '0'] + $line], now()))->not->toThrow(DomainException::class);
        expect(fn () => $create(supplier(), [$line], now()->addDay()))->toThrow(DomainException::class, 'future');
        expect(fn () => $create(supplier(), [$line], now(), now()->subDay()))->toThrow(DomainException::class, 'before it');

        $inactive = supplier('OLD');
        $inactive->update(['is_active' => false]);
        expect(fn () => $create($inactive, [$line], now()))->toThrow(DomainException::class, 'not an active supplier');
    });

    it('needs approval by default and by someone with the right', function () {
        $order = purchaseOrder(approve: false);
        $decide = app(DecidePurchaseOrder::class);

        expect(fn () => $decide->approve($order, $this->manager))->toThrow(DomainException::class, 'waiting for approval');

        $decide->submit($order);
        expect($order->fresh()->status)->toBe(PO::PendingApproval);
        expect(fn () => $decide->approve($order, $this->clerk))->toThrow(DomainException::class, 'not authorised');

        expect($decide->approve($order, $this->manager)->status)->toBe(PO::Approved);
    });

    it('is approved on submission when its total is within the configured limit', function () {
        app(ResolveSettings::class)->set('procurement.po_approval_threshold_minor', 5000000);

        $small = purchaseOrder(approve: false);
        expect(app(DecidePurchaseOrder::class)->submit($small)->status)->toBe(PO::Approved);

        $big = purchaseOrder([['inventory_item_id' => stockItem()->id, 'quantity' => '200', 'unit_cost_minor' => 35000]], approve: false);
        expect(app(DecidePurchaseOrder::class)->submit($big)->status)->toBe(PO::PendingApproval);
    });

    it('is created from an approved request, which is then ordered and released again if the order falls through', function () {
        $request = approvedRequest($this->clerk, $this->manager);
        $order = app(CreatePurchaseOrder::class)(supplier(), [['inventory_item_id' => stockItem()->id, 'quantity' => '100', 'unit_cost_minor' => 35000]], now()->startOfDay(), null, null, $request);

        expect($request->fresh()->status)->toBe(PR::Ordered)->and($request->orders)->toHaveCount(1);
        expect(fn () => app(CreatePurchaseOrder::class)(supplier(), [['inventory_item_id' => stockItem()->id, 'quantity' => '1', 'unit_cost_minor' => 1]], now(), null, null, $request))
            ->toThrow(DomainException::class, 'approved request');

        app(DecidePurchaseOrder::class)->submit($order);
        app(DecidePurchaseOrder::class)->reject($order, $this->manager, 'Too dear');

        expect($order->fresh()->status)->toBe(PO::Rejected)->and($request->fresh()->status)->toBe(PR::Approved);
    });

    it('cannot be cancelled once goods have arrived', function () {
        $order = purchaseOrder();
        receiveGoods($order, ['10']);

        expect(fn () => app(DecidePurchaseOrder::class)->cancel($order))->toThrow(DomainException::class, 'no longer be cancelled');

        $other = purchaseOrder(approve: false);
        expect(app(DecidePurchaseOrder::class)->cancel($other)->status)->toBe(PO::Cancelled);
    });
});

describe('goods receipts', function () {
    it('adds the delivery to stock through the ledger at the ordered cost', function () {
        $order = purchaseOrder();

        $receipt = receiveGoods($order, ['40']);

        $line = $receipt->lines->sole();
        $ledger = InventoryTransaction::sole();

        expect($receipt->number)->toStartWith('GR-')
            ->and($line->value_minor)->toBe(1400000)->and($line->inventory_transaction_id)->toBe($ledger->id)
            ->and($ledger->source_type)->toBe('goods_receipt')->and($ledger->source_id)->toBe($receipt->id)
            ->and(app(GetStockLevels::class)->total(stockItem()->id))->toBe('40.000')
            ->and($order->fresh()->status)->toBe(PO::PartiallyReceived);

        receiveGoods($order->fresh(), ['60']);
        expect($order->fresh()->status)->toBe(PO::Received)->and(app(GetStockLevels::class)->total(stockItem()->id))->toBe('100.000');
    });

    it('keeps batch, expiry and supplier of what arrived', function () {
        $vaccine = stockItem('VAC', ['tracks_batches' => true, 'tracks_expiry' => true]);
        $order = purchaseOrder([['inventory_item_id' => $vaccine->id, 'quantity' => '50', 'unit_cost_minor' => 1000]]);

        $receipt = receiveGoods($order, ['50'], extra: [['batch_number' => 'LOT-9', 'expiry_date' => now()->addYear()->toDateString()]]);

        $batch = InventoryBatch::sole();
        expect($receipt->lines->sole()->inventory_batch_id)->toBe($batch->id)
            ->and($batch->supplier_id)->toBe($order->supplier_id)
            ->and($batch->batch_number)->toBe('LOT-9');
    });

    it('refuses more than the order allows, and honours the tolerance', function () {
        $order = purchaseOrder();

        expect(fn () => receiveGoods($order, ['100.001']))->toThrow(DomainException::class, 'more than the order allows');
        expect(GoodsReceipt::count())->toBe(0);

        app(ResolveSettings::class)->set('procurement.over_receipt_tolerance_percent', 5);
        expect(receiveGoods($order, ['105'])->lines)->toHaveCount(1);
        expect(fn () => receiveGoods($order->fresh(), ['0.001']))->toThrow(DomainException::class, 'approved order');
    });

    it('only receives against approved orders and rolls back if a line fails', function () {
        $draft = purchaseOrder(approve: false);
        expect(fn () => receiveGoods($draft, ['1']))->toThrow(DomainException::class, 'approved order');

        $vaccine = stockItem('VAC', ['tracks_batches' => true]);
        $order = purchaseOrder([
            ['inventory_item_id' => stockItem()->id, 'quantity' => '10', 'unit_cost_minor' => 100],
            ['inventory_item_id' => $vaccine->id, 'quantity' => '10', 'unit_cost_minor' => 100],
        ]);

        expect(fn () => receiveGoods($order, ['10', '10']))->toThrow(DomainException::class, 'batch number');
        expect(GoodsReceipt::count())->toBe(0)->and(InventoryTransaction::count())->toBe(0)->and($order->fresh()->status)->toBe(PO::Approved);
    });

    it('can be voided, putting the stock back, unless the goods were used or invoiced', function () {
        $order = purchaseOrder();
        $receipt = receiveGoods($order, ['100']);

        app(VoidGoodsReceipt::class)($receipt, 'Wrong delivery');

        expect($receipt->fresh()->isVoided())->toBeTrue()
            ->and(app(GetStockLevels::class)->total(stockItem()->id))->toBe('0.000')
            ->and($order->fresh()->status)->toBe(PO::Approved);
        expect(fn () => app(VoidGoodsReceipt::class)($receipt, 'Again'))->toThrow(DomainException::class, 'already voided');

        $second = receiveGoods($order->fresh(), ['100']);
        issueStock(stockItem(), '5');
        expect(fn () => app(VoidGoodsReceipt::class)($second, 'Oops'))->toThrow(DomainException::class, 'already been used');

        $third = purchaseOrder();
        $delivered = receiveGoods($third, ['50']);
        app(RecordSupplierInvoice::class)($third, 'INV-3', now(), 1750000);
        expect(fn () => app(VoidGoodsReceipt::class)($delivered, 'Oops'))->toThrow(DomainException::class, 'Invoices already cover');
    });
});

describe('supplier invoices and payments', function () {
    beforeEach(function () {
        $this->order = purchaseOrder();
        receiveGoods($this->order, ['60']);   // 60 kg x 350.00 = 2,100,000
    });

    it('is matched to what was received, with the due date from the order terms', function () {
        $invoice = app(RecordSupplierInvoice::class)($this->order, ' INV-1 ', now()->startOfDay(), 2100000, 157500);

        expect($invoice->number)->toStartWith('SI-')->and($invoice->invoice_number)->toBe('INV-1')
            ->and($invoice->total_minor)->toBe(2257500)
            ->and($invoice->due_date->toDateString())->toBe(now()->addDays(30)->toDateString());

        expect(fn () => app(RecordSupplierInvoice::class)($this->order, 'INV-2', now(), 1))->toThrow(DomainException::class, 'more than has been received');
        expect(fn () => app(RecordSupplierInvoice::class)($this->order, 'INV-1', now(), 1))->toThrow(DomainException::class, 'already been recorded');
        expect(fn () => app(RecordSupplierInvoice::class)($this->order, 'INV-3', now(), 0))->toThrow(DomainException::class, 'positive');
    });

    it('cannot be raised for goods not yet received, and a voided invoice frees the amount', function () {
        $none = purchaseOrder();
        expect(fn () => app(RecordSupplierInvoice::class)($none, 'X-1', now(), 100))->toThrow(DomainException::class, 'more than has been received');

        $invoice = app(RecordSupplierInvoice::class)($this->order, 'INV-1', now(), 2100000);
        app(VoidSupplierInvoice::class)($invoice, 'Wrong supplier invoice');

        expect($invoice->fresh()->isVoided())->toBeTrue()->and($invoice->fresh()->balanceMinor())->toBe(0);
        expect(app(RecordSupplierInvoice::class)($this->order, 'INV-1B', now(), 2100000))->not->toBeNull();
    });

    it('pays an invoice in parts and never beyond what is owed', function () {
        $invoice = app(RecordSupplierInvoice::class)($this->order, 'INV-1', now()->subDay()->startOfDay(), 2100000);
        $pay = app(RecordSupplierPayment::class);

        $first = $pay($invoice, 1000000, now()->startOfDay(), PaymentMethod::BankTransfer, 'TRF-1');

        expect($first->number)->toStartWith('SP-')->and($first->status)->toBe(PaymentStatus::Paid)
            ->and($invoice->fresh()->balanceMinor())->toBe(1100000);
        expect(fn () => $pay($invoice, 1100001, now(), PaymentMethod::Cash))->toThrow(DomainException::class, 'still owed');
        expect(fn () => $pay($invoice, 0, now(), PaymentMethod::Cash))->toThrow(DomainException::class, 'positive');
        expect(fn () => $pay($invoice, 1, now()->subDays(5), PaymentMethod::Cash))->toThrow(DomainException::class, 'before the invoice');

        $pay($invoice, 1100000, now()->startOfDay(), PaymentMethod::Cash);
        expect($invoice->fresh()->balanceMinor())->toBe(0);
        expect(fn () => app(VoidSupplierInvoice::class)($invoice, 'No'))->toThrow(DomainException::class, 'Payments are recorded');
    });

    it('holds a large payment for approval and counts it against the invoice meanwhile', function () {
        app(ResolveSettings::class)->set('procurement.payment_approval_threshold_minor', 1000000);
        $invoice = app(RecordSupplierInvoice::class)($this->order, 'INV-1', now()->startOfDay(), 2100000);
        $decide = app(DecideSupplierPayment::class);

        $small = app(RecordSupplierPayment::class)($invoice, 1000000, now(), PaymentMethod::Cash, null, $this->clerk);
        $large = app(RecordSupplierPayment::class)($invoice, 1100000, now(), PaymentMethod::BankTransfer, null, $this->clerk);

        expect($small->status)->toBe(PaymentStatus::Paid)->and($large->status)->toBe(PaymentStatus::PendingApproval)
            ->and($invoice->fresh()->balanceMinor())->toBe(1100000);   // pending is not yet paid ...
        expect(fn () => app(RecordSupplierPayment::class)($invoice, 1, now(), PaymentMethod::Cash))->toThrow(DomainException::class, 'still owed');   // ... but is reserved

        expect(fn () => $decide->approve($large, $this->clerk))->toThrow(DomainException::class, 'not authorised');
        $decide->approve($large, $this->manager, 'OK');

        expect($large->fresh()->status)->toBe(PaymentStatus::Paid)->and($invoice->fresh()->balanceMinor())->toBe(0);
        expect(fn () => $decide->approve($large, $this->manager))->toThrow(DomainException::class, 'waiting for approval');
    });

    it('releases the amount when a held payment is rejected, and voids a payment made in error', function () {
        app(ResolveSettings::class)->set('procurement.payment_approval_threshold_minor', 100);
        $invoice = app(RecordSupplierInvoice::class)($this->order, 'INV-1', now()->startOfDay(), 2100000);
        $decide = app(DecideSupplierPayment::class);
        $payment = app(RecordSupplierPayment::class)($invoice, 2100000, now(), PaymentMethod::Cheque);

        expect(fn () => $decide->reject($payment, $this->manager, ''))->toThrow(DomainException::class, 'reason');
        $decide->reject($payment, $this->manager, 'Wrong account');
        expect($payment->fresh()->status)->toBe(PaymentStatus::Rejected);

        $again = app(RecordSupplierPayment::class)($invoice, 2100000, now(), PaymentMethod::Cheque);
        $decide->approve($again, $this->manager);
        $decide->void($again->fresh(), 'Bounced');

        expect($again->fresh()->status)->toBe(PaymentStatus::Voided)->and($invoice->fresh()->balanceMinor())->toBe(2100000);
        expect(fn () => $decide->void($again->fresh(), 'Twice'))->toThrow(DomainException::class, 'has been made');
        expect(fn () => $again->fresh()->update(['amount_minor' => 1]))->toThrow(LogicException::class);
    });

    it('reports what each supplier is owed and what is overdue', function () {
        $overdue = app(RecordSupplierInvoice::class)($this->order, 'OLD-1', now()->subDays(60)->startOfDay(), 1000000);
        app(RecordSupplierInvoice::class)($this->order, 'NEW-1', now()->startOfDay(), 1100000);
        app(RecordSupplierPayment::class)($overdue, 400000, now()->startOfDay(), PaymentMethod::Cash);

        $row = app(GetSupplierBalances::class)()->sole();

        expect($row['invoiced'])->toBe(2100000)->and($row['paid'])->toBe(400000)
            ->and($row['outstanding'])->toBe(1700000)->and($row['overdue'])->toBe(600000);
    });
});

it('traces a purchase from request to payment', function () {
    $request = approvedRequest($this->clerk, $this->manager);
    $order = app(CreatePurchaseOrder::class)(supplier(), [['inventory_item_id' => stockItem()->id, 'quantity' => '100', 'unit_cost_minor' => 35000]], now()->subDay()->startOfDay(), null, null, $request);
    app(DecidePurchaseOrder::class)->submit($order);
    app(DecidePurchaseOrder::class)->approve($order, $this->manager);
    receiveGoods($order->fresh(), ['100']);
    $invoice = app(RecordSupplierInvoice::class)($order, 'INV-1', now()->startOfDay(), 3500000);
    app(RecordSupplierPayment::class)($invoice, 3500000, now()->startOfDay(), PaymentMethod::BankTransfer);

    $trace = app(GetPurchaseTrace::class)($order->fresh());

    expect($trace['request']->number)->toBe($request->number)
        ->and($trace['receipts'])->toHaveCount(1)
        ->and($trace['invoices'])->toHaveCount(1)
        ->and($trace['payments'])->toHaveCount(1)
        ->and([$trace['received_value'], $trace['invoiced'], $trace['paid']])->toBe([3500000, 3500000, 3500000])
        ->and($trace['order']->status)->toBe(PO::Received);
});

it('grants purchasing rights by role', function () {
    $accountant = userWithRole('Accountant');

    expect($this->clerk->can('procurement.create'))->toBeTrue()->and($this->clerk->can('procurement.approve'))->toBeFalse()
        ->and($accountant->can('procurement.edit'))->toBeTrue()->and($accountant->can('procurement.approve'))->toBeFalse()
        ->and($this->manager->can('procurement.approve'))->toBeTrue()
        ->and(farmWorker()->can('procurement.view'))->toBeFalse()
        ->and($this->manager->can('delete', PurchaseOrder::class))->toBeFalse();
});
