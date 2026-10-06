<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Payment;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Voids a payment entered in error; the invoices it settled owe their money again and any deposit it left disappears. */
class VoidCustomerPayment
{
    public function __invoke(Payment $payment, string $reason, ?User $actor = null): Payment
    {
        trim($reason) !== '' || throw new DomainException('A reason is required to void a payment.', 'reason_required');

        return DB::transaction(function () use ($payment, $reason, $actor) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $payment->isVoided() && throw new DomainException('This payment is already voided.', 'already_voided');

            $payment->forceFill(['voided_at' => now(), 'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason)])->save();

            return $payment;
        });
    }
}
