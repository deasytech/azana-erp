<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Concerns\ChecksInventoryApproval;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectStockAdjustment
{
    use ChecksInventoryApproval;

    public function __invoke(StockAdjustment $adjustment, User $approver, string $reason): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $approver, $reason) {
            $adjustment = StockAdjustment::lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->status !== ApprovalStatus::Pending) {
                throw new DomainException('This adjustment has already been decided.', 'already_decided');
            }

            if (trim($reason) === '') {
                throw new DomainException('A reason is required to reject an adjustment.', 'reason_required');
            }

            $this->assertMayDecide($approver, $adjustment->requested_by, 'stock adjustment');
            $adjustment->update(['status' => ApprovalStatus::Rejected, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => trim($reason)]);

            return $adjustment;
        });
    }
}
