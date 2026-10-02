<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Concerns\ChecksInventoryApproval;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ApprovalStatus;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Approves a requested adjustment and posts it to the ledger. */
class ApproveStockAdjustment
{
    use ChecksInventoryApproval;

    public function __construct(private readonly PostInventoryTransaction $post) {}

    public function __invoke(StockAdjustment $adjustment, User $approver, ?string $notes = null): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $approver, $notes) {
            $adjustment = StockAdjustment::lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->status !== ApprovalStatus::Pending) {
                throw new DomainException('This adjustment has already been decided.', 'already_decided');
            }

            $this->assertMayDecide($approver, $adjustment->requested_by, 'stock adjustment');

            ($this->post)(InventoryTransactionType::Adjustment, $adjustment->inventory_item_id, $adjustment->inventory_location_id, (string) $adjustment->quantity, now()->startOfDay(), [
                'batch' => $adjustment->inventory_batch_id,
                'reason' => "Adjustment {$adjustment->number}: {$adjustment->reason}",
                'source_type' => 'stock_adjustment',
                'source_id' => $adjustment->id,
            ], $approver);

            $adjustment->update(['status' => ApprovalStatus::Approved, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);

            return $adjustment;
        });
    }
}
