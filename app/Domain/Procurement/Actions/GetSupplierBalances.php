<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Supplier\Models\Supplier;
use Illuminate\Support\Collection;

/** What each supplier has invoiced, been paid and is still owed (minor units), with the overdue part. */
class GetSupplierBalances
{
    /** @return Collection<int, array{supplier: Supplier, invoiced: int, paid: int, outstanding: int, overdue: int}> suppliers with something owed or invoiced */
    public function __invoke(): Collection
    {
        return SupplierInvoice::with(['supplier', 'payments'])->whereNull('voided_at')->get()->groupBy('supplier_id')
            ->map(function (Collection $invoices) {
                $paid = $invoices->sum(fn (SupplierInvoice $i) => $i->paidMinor());

                return [
                    'supplier' => $invoices->first()->supplier,
                    'invoiced' => $invoices->sum('total_minor'),
                    'paid' => $paid,
                    'outstanding' => $invoices->sum('total_minor') - $paid,
                    'overdue' => $invoices->filter->isOverdue()->sum(fn (SupplierInvoice $i) => $i->balanceMinor()),
                ];
            })
            ->sortByDesc('outstanding')->values();
    }
}
