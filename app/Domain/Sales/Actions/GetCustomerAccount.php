<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Enums\CreditStatus;

/** What a customer owes, what is overdue, what they hold on deposit, and how much more credit they can use. */
class GetCustomerAccount
{
    /** @return array{outstanding: int, overdue: int, deposit: int, credit_limit: int, credit_status: CreditStatus, headroom: int} headroom may be negative (over the limit); all in minor units */
    public function __invoke(Customer $customer): array
    {
        $invoices = Invoice::where('customer_id', $customer->id)->with('allocations.payment')->get();
        $outstanding = $invoices->sum(fn (Invoice $i) => $i->balanceMinor());
        $overdue = $invoices->filter->isOverdue()->sum(fn (Invoice $i) => $i->balanceMinor());
        $deposit = Payment::where('customer_id', $customer->id)->whereNull('voided_at')->get()->sum(fn (Payment $p) => $p->unallocatedMinor());

        // Credit counts only while it is approved; a customer on hold or without credit has a limit of zero.
        $limit = $customer->credit_status === CreditStatus::Approved ? $customer->credit_limit_minor : 0;

        return [
            'outstanding' => $outstanding,
            'overdue' => $overdue,
            'deposit' => $deposit,
            'credit_limit' => $limit,
            'credit_status' => $customer->credit_status,
            'headroom' => $limit - $outstanding + $deposit,
        ];
    }
}
