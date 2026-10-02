<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Concerns\ChecksProcurementApproval;
use App\Domain\Procurement\Models\SupplierPayment;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Approves or rejects a payment held for approval, and voids a payment that was made in error. */
class DecideSupplierPayment
{
    use ChecksProcurementApproval;

    public function approve(SupplierPayment $payment, User $approver, ?string $notes = null): SupplierPayment
    {
        return $this->decide($payment, $approver, PaymentStatus::Paid, $notes);
    }

    public function reject(SupplierPayment $payment, User $approver, string $reason): SupplierPayment
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to reject a payment.', 'reason_required');
        }

        return $this->decide($payment, $approver, PaymentStatus::Rejected, trim($reason));
    }

    public function void(SupplierPayment $payment, string $reason, ?User $actor = null): SupplierPayment
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void a payment.', 'reason_required');
        }

        return DB::transaction(function () use ($payment, $reason, $actor) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Paid) {
                throw new DomainException('Only a payment that has been made can be voided.', 'payment_state');
            }

            $payment->update([
                'status' => PaymentStatus::Voided, 'voided_at' => now(),
                'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason),
            ]);

            return $payment;
        });
    }

    private function decide(SupplierPayment $payment, User $approver, PaymentStatus $to, ?string $notes): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $approver, $to, $notes) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::PendingApproval) {
                throw new DomainException('Only a payment waiting for approval can be decided.', 'payment_state');
            }

            $this->assertMayDecide($approver, $payment->created_by, 'supplier payment');
            $payment->update(['status' => $to, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);

            return $payment;
        });
    }
}
