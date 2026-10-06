<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

/** Pays an invoice from the money its customer holds on deposit, oldest receipt first. */
class AllocateCustomerFunds
{
    /**
     * @param  ?int  $limitMinor  most to use (default: as much as the invoice still needs)
     * @return int minor units applied
     */
    public function __invoke(Invoice $invoice, ?int $limitMinor = null): int
    {
        return DB::transaction(function () use ($invoice, $limitMinor) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $need = min($invoice->balanceMinor(), $limitMinor ?? PHP_INT_MAX);
            $applied = 0;

            foreach (Payment::where('customer_id', $invoice->customer_id)->whereNull('voided_at')->orderBy('received_on')->orderBy('id')->lockForUpdate()->get() as $payment) {
                if ($need <= 0) {
                    break;
                }

                $take = min($need, $payment->unallocatedMinor());

                if ($take > 0) {
                    PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount_minor' => $take]);
                    $need -= $take;
                    $applied += $take;
                }
            }

            return $applied;
        });
    }
}
