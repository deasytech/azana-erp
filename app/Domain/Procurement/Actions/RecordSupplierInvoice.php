<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records the invoice a supplier sent for an order. The goods amount cannot exceed the value actually received
 * and not yet invoiced; tax is added on top. The due date follows the payment terms copied onto the order.
 */
class RecordSupplierInvoice
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly MatchSupplierInvoices $matching) {}

    public function __invoke(PurchaseOrder $order, string $supplierInvoiceNumber, CarbonInterface $invoiceDate, int $subtotalMinor, int $taxMinor = 0, ?string $notes = null, ?User $actor = null): SupplierInvoice
    {
        $number = trim($supplierInvoiceNumber);

        if ($number === '') {
            throw new DomainException('Enter the supplier\'s invoice number.', 'invoice_number');
        }

        if ($subtotalMinor <= 0 || $taxMinor < 0) {
            throw new DomainException('The invoice amount must be positive and the tax cannot be negative.', 'invoice_amount');
        }

        if ($invoiceDate->gt(now()->addMinutes(5))) {
            throw new DomainException('The invoice cannot be dated in the future.', 'invoice_date');
        }

        return DB::transaction(function () use ($order, $number, $invoiceDate, $subtotalMinor, $taxMinor, $notes, $actor) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if (SupplierInvoice::where('supplier_id', $order->supplier_id)->where('invoice_number', $number)->exists()) {
                throw new DomainException("Invoice {$number} from this supplier has already been recorded.", 'duplicate_invoice');
            }

            $open = $this->matching->uninvoiced($order);

            if ($subtotalMinor > $open) {
                throw new DomainException("The invoice is for more than has been received and not yet invoiced ({$open} minor units).", 'invoice_exceeds_receipts');
            }

            return $this->store($order, $number, $invoiceDate, $subtotalMinor, $taxMinor, $notes, $actor);
        });
    }

    /** Creates the invoice, turning a race on the (supplier, invoice number) unique index into the usual error. */
    private function store(PurchaseOrder $order, string $number, CarbonInterface $invoiceDate, int $subtotalMinor, int $taxMinor, ?string $notes, ?User $actor): SupplierInvoice
    {
        try {
            return SupplierInvoice::create([
                'number' => sprintf('SI-%06d', ($this->nextNumber)('supplier_invoice')),
                'supplier_id' => $order->supplier_id,
                'purchase_order_id' => $order->id,
                'invoice_number' => $number,
                'invoice_date' => $invoiceDate,
                'due_date' => $invoiceDate->copy()->startOfDay()->addDays($order->payment_terms_days),
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'total_minor' => $subtotalMinor + $taxMinor,
                'notes' => $notes,
                'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            if (! $this->isDuplicateInvoiceViolation($e)) {
                throw $e;
            }

            throw new DomainException("Invoice {$number} from this supplier has already been recorded.", 'duplicate_invoice');
        }
    }

    /** Whether the violated unique index is the composite one over supplier_id and invoice_number. */
    private function isDuplicateInvoiceViolation(UniqueConstraintViolationException $e): bool
    {
        if (in_array('supplier_id', $e->columns, true) && in_array('invoice_number', $e->columns, true)) {
            return true;
        }

        return $e->index !== null && str_contains($e->index, 'supplier_id') && str_contains($e->index, 'invoice_number');
    }
}
