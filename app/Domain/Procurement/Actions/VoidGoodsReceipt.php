<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Inventory\Actions\ReverseInventoryTransaction;
use App\Domain\Procurement\Models\GoodsReceipt;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Voids a delivery entered in error by reversing its stock lines. Refused when any of the goods have already
 * been used, or when invoices already claim more than would remain received.
 */
class VoidGoodsReceipt
{
    public function __construct(
        private readonly ReverseInventoryTransaction $reverse,
        private readonly SyncPurchaseOrderReceiptStatus $sync,
        private readonly MatchSupplierInvoices $matching,
    ) {}

    public function __invoke(GoodsReceipt $receipt, string $reason, ?User $actor = null): GoodsReceipt
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void a goods receipt.', 'reason_required');
        }

        return DB::transaction(function () use ($receipt, $reason, $actor) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($receipt->purchase_order_id);
            $receipt = GoodsReceipt::with('lines.transaction')->findOrFail($receipt->id);

            if ($receipt->isVoided()) {
                throw new DomainException('This goods receipt is already voided.', 'already_voided');
            }

            $voidedValue = $receipt->lines->sum('value_minor');

            if ($this->matching->invoicedSubtotal($order) > $this->matching->receivedValue($order) - $voidedValue) {
                throw new DomainException('Invoices already cover these goods: void or reduce the invoice first.', 'receipt_invoiced');
            }

            foreach ($receipt->lines as $line) {
                ($this->reverse)($line->transaction, "Goods receipt {$receipt->number} voided: ".trim($reason), $actor);
            }

            $receipt->forceFill(['voided_at' => now(), 'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason)])->save();
            ($this->sync)($order->refresh());

            return $receipt;
        });
    }
}
