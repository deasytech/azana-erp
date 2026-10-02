<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Procurement\Models\SupplierPayment;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pays (part of) a supplier invoice. A payment above the configured limit (`procurement.payment_approval_threshold_minor`)
 * is held for approval and does not count as paid until approved; either way it reserves its amount, so an invoice
 * can never be paid more than once over.
 */
class RecordSupplierPayment
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly ResolveSettings $settings) {}

    public function __invoke(SupplierInvoice $invoice, int $amountMinor, CarbonInterface $paidOn, PaymentMethod $method, ?string $reference = null, ?User $actor = null): SupplierPayment
    {
        if ($amountMinor <= 0) {
            throw new DomainException('The payment must be a positive amount.', 'payment_amount');
        }

        if ($paidOn->gt(now()->addMinutes(5))) {
            throw new DomainException('A payment cannot be dated in the future.', 'payment_date');
        }

        return DB::transaction(function () use ($invoice, $amountMinor, $paidOn, $method, $reference, $actor) {
            $invoice = SupplierInvoice::lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->isVoided()) {
                throw new DomainException("{$invoice->number} is voided.", 'invoice_voided');
            }

            if ($paidOn->lt($invoice->invoice_date)) {
                throw new DomainException('A payment cannot be dated before the invoice.', 'payment_date');
            }

            $open = $invoice->total_minor - $invoice->committedMinor();

            if ($amountMinor > $open) {
                throw new DomainException("That is more than the {$open} (minor units) still owed on {$invoice->number}.", 'overpayment');
            }

            $needsApproval = $amountMinor > (int) $this->settings->get('procurement.payment_approval_threshold_minor');

            return SupplierPayment::create([
                'number' => sprintf('SP-%06d', ($this->nextNumber)('supplier_payment')),
                'supplier_invoice_id' => $invoice->id,
                'amount_minor' => $amountMinor,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference,
                'status' => $needsApproval ? PaymentStatus::PendingApproval : PaymentStatus::Paid,
                'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);
        });
    }
}
