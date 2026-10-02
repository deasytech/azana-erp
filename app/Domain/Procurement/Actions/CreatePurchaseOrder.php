<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Farm\Models\Farm;
use App\Domain\Procurement\Concerns\CleansPurchaseLines;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseRequest;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PurchaseRequestStatus;
use App\Models\User;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Drafts a purchase order for a supplier, optionally from an approved purchase request (which is then marked
 * ordered). Each line: inventory_item_id, quantity, unit_cost_minor. The supplier's payment terms are copied
 * onto the order so later changes to the supplier do not alter it.
 */
class CreatePurchaseOrder
{
    use CleansPurchaseLines;

    public function __construct(private readonly NextNumber $nextNumber) {}

    /** @param list<array<string, mixed>> $lines */
    public function __invoke(Supplier|int $supplier, array $lines, CarbonInterface $orderedOn, ?CarbonInterface $expectedOn = null, ?string $notes = null, PurchaseRequest|int|null $request = null, ?User $actor = null): PurchaseOrder
    {
        $lines = $this->cleanLines($lines);

        foreach ($lines as $line) {
            if (! isset($line['unit_cost_minor']) || (int) $line['unit_cost_minor'] < 0 || ! is_numeric($line['unit_cost_minor'])) {
                throw new DomainException('Give a unit cost (zero or more) for every line.', 'line_cost');
            }
        }

        if ($orderedOn->gt(now()->addMinutes(5)) || ($expectedOn && $expectedOn->lt($orderedOn->copy()->startOfDay()))) {
            throw new DomainException('The order cannot be dated in the future, and delivery cannot be expected before it.', 'order_dates');
        }

        return DB::transaction(function () use ($supplier, $lines, $orderedOn, $expectedOn, $notes, $request, $actor) {
            $supplier = Supplier::findOrFail($supplier instanceof Supplier ? $supplier->id : $supplier);

            if (! $supplier->is_active) {
                throw new DomainException("{$supplier->name} is not an active supplier.", 'inactive_supplier');
            }

            $request = $request === null ? null : PurchaseRequest::lockForUpdate()->findOrFail($request instanceof PurchaseRequest ? $request->id : $request);

            if ($request && $request->status !== PurchaseRequestStatus::Approved) {
                throw new DomainException("{$request->number} is not an approved request waiting to be ordered.", 'request_not_approved');
            }

            $rows = array_map(fn (array $l) => $l + ['line_total_minor' => Ratio::toWhole(bcmul($l['quantity'], (string) (int) $l['unit_cost_minor'], 6))], $lines);

            $order = PurchaseOrder::create([
                'number' => sprintf('PO-%06d', ($this->nextNumber)('purchase_order')),
                'supplier_id' => $supplier->id,
                'purchase_request_id' => $request?->id,
                'ordered_on' => $orderedOn,
                'expected_on' => $expectedOn,
                'payment_terms_days' => $supplier->payment_terms_days,
                'currency_code' => Farm::defaultCurrency(),
                'total_minor' => array_sum(array_column($rows, 'line_total_minor')),
                'notes' => $notes,
                'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            foreach ($rows as $row) {
                $order->lines()->create([
                    'inventory_item_id' => $row['inventory_item_id'],
                    'quantity' => $row['quantity'],
                    'unit_cost_minor' => (int) $row['unit_cost_minor'],
                    'line_total_minor' => $row['line_total_minor'],
                ]);
            }

            $request?->update(['status' => PurchaseRequestStatus::Ordered]);

            return $order->load('lines');
        });
    }
}
