<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Models\GoodsReceiptLine;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\SupplierInvoice;

/** Three-way match figures for an order: what was received, and how much of that has been invoiced (minor units). */
class MatchSupplierInvoices
{
    public function receivedValue(PurchaseOrder $order): int
    {
        return (int) GoodsReceiptLine::whereHas('receipt', fn ($q) => $q->where('purchase_order_id', $order->id)->whereNull('voided_at'))->sum('value_minor');
    }

    public function invoicedSubtotal(PurchaseOrder $order): int
    {
        return (int) SupplierInvoice::where('purchase_order_id', $order->id)->whereNull('voided_at')->sum('subtotal_minor');
    }

    /** Value received that no invoice covers yet. */
    public function uninvoiced(PurchaseOrder $order): int
    {
        return $this->receivedValue($order) - $this->invoicedSubtotal($order);
    }
}
