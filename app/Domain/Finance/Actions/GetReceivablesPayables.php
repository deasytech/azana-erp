<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Sales\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What customers owe (unpaid sales invoices) and what is owed to suppliers (unpaid supplier invoices), each aged by
 * days past due: not yet due, 1-30, 31-60, over 60. Read from the operational documents, so it is always current.
 */
class GetReceivablesPayables
{
    public const BUCKETS = ['current' => 'Not yet due', '1_30' => '1-30 days', '31_60' => '31-60 days', 'over_60' => 'Over 60 days'];

    /** @return array{receivables: array<string, mixed>, payables: array<string, mixed>} */
    public function __invoke(?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();

        return [
            'receivables' => $this->aged(Invoice::with(['customer', 'allocations.payment'])->get()->map(fn (Invoice $i) => [$i->customer->name, $i->due_on, $i->balanceMinor()]), $asOf),
            'payables' => $this->aged(SupplierInvoice::with('supplier')->whereNull('voided_at')->get()->map(fn (SupplierInvoice $i) => [$i->supplier->name, $i->due_date, $i->balanceMinor()]), $asOf),
        ];
    }

    /**
     * @param  Collection<int, array{0: string, 1: CarbonInterface, 2: int}>  $items
     * @return array{total_minor: int, buckets: array<string, int>, parties: list<array<string, mixed>>}
     */
    private function aged(Collection $items, CarbonInterface $asOf): array
    {
        $parties = [];

        foreach ($items->filter(fn ($i) => $i[2] > 0) as [$name, $due, $balance]) {
            $late = (int) $due->startOfDay()->diffInDays($asOf->copy()->startOfDay(), false);
            $bucket = match (true) {
                $late <= 0 => 'current', $late <= 30 => '1_30', $late <= 60 => '31_60', default => 'over_60',
            };
            $parties[$name] ??= ['name' => $name, 'total_minor' => 0, ...array_map(fn () => 0, self::BUCKETS)];
            $parties[$name][$bucket] += $balance;
            $parties[$name]['total_minor'] += $balance;
        }

        $parties = collect($parties)->sortByDesc('total_minor')->values();

        return [
            'total_minor' => (int) $parties->sum('total_minor'),
            'buckets' => array_map(fn ($key) => (int) $parties->sum($key), array_combine(array_keys(self::BUCKETS), array_keys(self::BUCKETS))),
            'parties' => $parties->all(),
        ];
    }
}
