<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Procurement\Models\SupplierPayment;
use App\Domain\Supplier\Models\Supplier;
use App\Enums\PaymentStatus;
use Illuminate\Support\Collection;

/** What each supplier has invoiced, been paid and is still owed (minor units), with the overdue part. */
class GetSupplierBalances
{
    /** @return Collection<int, array{supplier: Supplier, invoiced: int, paid: int, outstanding: int, overdue: int}> suppliers with something owed or invoiced */
    public function __invoke(): Collection
    {
        $payments = SupplierPayment::query()
            ->selectRaw('supplier_invoice_id, sum(amount_minor) as paid_minor')
            ->where('status', PaymentStatus::Paid)
            ->groupBy('supplier_invoice_id');

        $rows = SupplierInvoice::query()
            ->leftJoinSub($payments, 'payment_totals', 'payment_totals.supplier_invoice_id', '=', 'supplier_invoices.id')
            ->whereNull('supplier_invoices.voided_at')
            ->groupBy('supplier_invoices.supplier_id')
            ->selectRaw('supplier_invoices.supplier_id as supplier_id')
            ->selectRaw('sum(supplier_invoices.total_minor) as invoiced')
            ->selectRaw('sum(coalesce(payment_totals.paid_minor, 0)) as paid')
            ->selectRaw('sum(supplier_invoices.total_minor) - sum(coalesce(payment_totals.paid_minor, 0)) as outstanding')
            ->selectRaw(
                'sum(case when supplier_invoices.due_date < ? and supplier_invoices.total_minor > coalesce(payment_totals.paid_minor, 0)'
                .' then supplier_invoices.total_minor - coalesce(payment_totals.paid_minor, 0) else 0 end) as overdue',
                [now()->startOfDay()],
            )
            ->get();

        $suppliers = Supplier::query()->whereIn('id', $rows->pluck('supplier_id'))->get()->keyBy('id');

        return $rows
            ->map(fn (object $row) => [
                'supplier' => $suppliers[$row->supplier_id],
                'invoiced' => (int) $row->invoiced,
                'paid' => (int) $row->paid,
                'outstanding' => (int) $row->outstanding,
                'overdue' => (int) $row->overdue,
            ])
            ->sortByDesc('outstanding')
            ->values();
    }
}
