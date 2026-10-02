<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VoidSupplierInvoice
{
    public function __invoke(SupplierInvoice $invoice, string $reason, ?User $actor = null): SupplierInvoice
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void an invoice.', 'reason_required');
        }

        return DB::transaction(function () use ($invoice, $reason, $actor) {
            $invoice = SupplierInvoice::lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->isVoided()) {
                throw new DomainException('This invoice is already voided.', 'already_voided');
            }

            if ($invoice->committedMinor() > 0) {
                throw new DomainException('Payments are recorded against this invoice: void them first.', 'invoice_has_payments');
            }

            $invoice->forceFill(['voided_at' => now(), 'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason)])->save();

            return $invoice;
        });
    }
}
