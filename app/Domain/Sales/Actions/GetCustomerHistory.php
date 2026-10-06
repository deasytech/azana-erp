<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Everything a customer has ordered, been invoiced and paid, newest first. */
class GetCustomerHistory
{
    /** @return Collection<int, array{date: CarbonInterface, type: string, number: string, amount_minor: int, status: string, ref_id: int}> */
    public function __invoke(Customer $customer): Collection
    {
        $orders = $customer->orders()->get()->map(fn ($o) => ['date' => $o->ordered_on, 'type' => 'Order', 'number' => $o->number, 'amount_minor' => $o->total_minor, 'status' => $o->status->label(), 'ref_id' => $o->id]);
        $invoices = $customer->invoices()->with('allocations.payment')->get()->map(fn ($i) => ['date' => $i->issued_on, 'type' => 'Invoice', 'number' => $i->number, 'amount_minor' => $i->total_minor, 'status' => $i->balanceMinor() === 0 ? 'Paid' : ($i->isOverdue() ? 'Overdue' : 'Open'), 'ref_id' => $i->id]);
        $payments = $customer->payments()->get()->map(fn ($p) => ['date' => $p->received_on, 'type' => 'Payment', 'number' => $p->number, 'amount_minor' => $p->amount_minor, 'status' => $p->isVoided() ? 'Voided' : 'Received', 'ref_id' => $p->id]);

        return $orders->concat($invoices)->concat($payments)->sortByDesc(fn (array $row) => [$row['date']->timestamp, $row['ref_id']])->values();
    }
}
