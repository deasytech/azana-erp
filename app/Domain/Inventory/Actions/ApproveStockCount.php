<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Concerns\ChecksInventoryApproval;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use App\Enums\StockCountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Approves a submitted count and posts each variance to the ledger as an adjustment. Needs inventory.approve and
 * (by default) someone other than who submitted it. The adjustment is the variance found at count time, so stock
 * used since the count must still cover a shortfall or the approval is refused.
 */
class ApproveStockCount
{
    use ChecksInventoryApproval;

    public function __construct(private readonly PostInventoryTransaction $post) {}

    public function __invoke(StockCount $count, User $approver, ?string $notes = null): StockCount
    {
        return DB::transaction(function () use ($count, $approver, $notes) {
            $count = StockCount::lockForUpdate()->with('lines')->findOrFail($count->id);

            if ($count->status !== StockCountStatus::Submitted) {
                throw new DomainException('Only a submitted count can be approved.', 'count_not_submitted');
            }

            $this->assertMayDecide($approver, $count->submitted_by, 'stock count');

            foreach ($count->lines->filter(fn ($l) => bccomp((string) $l->variance_quantity, '0', 3) !== 0) as $line) {
                $row = ($this->post)(InventoryTransactionType::Adjustment, $line->inventory_item_id, $count->inventory_location_id, (string) $line->variance_quantity, $count->counted_on, [
                    'batch' => $line->inventory_batch_id,
                    'reason' => "Stock count {$count->number}: {$line->reason}",
                    'source_type' => 'stock_count',
                    'source_id' => $count->id,
                ], $approver);

                $line->update(['variance_value_minor' => $row->value_minor]);
            }

            $count->update(['status' => StockCountStatus::Approved, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);

            return $count;
        });
    }
}
