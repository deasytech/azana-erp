<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Concerns\ChecksInventoryApproval;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\StockCountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Rejects a submitted count (no stock changes); the store may then be counted again. */
class RejectStockCount
{
    use ChecksInventoryApproval;

    public function __invoke(StockCount $count, User $approver, string $reason): StockCount
    {
        return DB::transaction(function () use ($count, $approver, $reason) {
            $count = StockCount::lockForUpdate()->findOrFail($count->id);

            if ($count->status !== StockCountStatus::Submitted) {
                throw new DomainException('Only a submitted count can be rejected.', 'count_not_submitted');
            }

            if (trim($reason) === '') {
                throw new DomainException('A reason is required to reject a count.', 'reason_required');
            }

            $this->assertMayDecide($approver, $count->submitted_by, 'stock count');
            $count->update(['status' => StockCountStatus::Rejected, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => trim($reason)]);

            return $count;
        });
    }
}
