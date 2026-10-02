<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseRequest;
use Illuminate\Support\Collection;

/** Everything on the road from request to payment for one order, in the order it happened. */
class GetPurchaseTrace
{
    public function __construct(private readonly MatchSupplierInvoices $matching) {}

    /** @return array{order: PurchaseOrder, request: ?PurchaseRequest, receipts: Collection, invoices: Collection, payments: Collection, received_value: int, invoiced: int, paid: int} */
    public function __invoke(PurchaseOrder $order): array
    {
        $order->load(['supplier', 'request.requestedBy', 'lines.item', 'receipts.lines.batch', 'receipts.location', 'invoices.payments']);
        $invoices = $order->invoices->whereNull('voided_at')->values();

        return [
            'order' => $order,
            'request' => $order->request,
            'receipts' => $order->receipts->sortBy('received_on')->values(),
            'invoices' => $order->invoices->sortBy('invoice_date')->values(),
            'payments' => $order->invoices->flatMap->payments->sortBy('paid_on')->values(),
            'received_value' => $this->matching->receivedValue($order),
            'invoiced' => $invoices->sum('total_minor'),
            'paid' => $invoices->sum(fn ($invoice) => $invoice->paidMinor()),
        ];
    }
}
