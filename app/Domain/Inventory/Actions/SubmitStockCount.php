<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\StockCount;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\StockCountStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Sends a finished count for approval: every line must be counted and every variance explained. */
class SubmitStockCount
{
    public function __invoke(StockCount $count, ?User $actor = null): StockCount
    {
        return DB::transaction(function () use ($count, $actor) {
            $count = StockCount::lockForUpdate()->findOrFail($count->id);

            if ($count->status !== StockCountStatus::Draft) {
                throw new DomainException('This count has already been submitted.', 'count_not_draft');
            }

            $lines = $count->lines()->get();

            if ($lines->isEmpty()) {
                throw new DomainException('There is nothing to count: add the stock you found.', 'count_empty');
            }

            if ($lines->contains(fn ($l) => $l->counted_quantity === null)) {
                throw new DomainException('Enter a counted quantity for every line (use 0 for stock that is gone).', 'count_incomplete');
            }

            if ($lines->contains(fn ($l) => bccomp((string) $l->variance_quantity, '0', 3) !== 0 && $l->reason === null)) {
                throw new DomainException('Give a reason for every line that differs from the system.', 'count_reason_required');
            }

            $count->update(['status' => StockCountStatus::Submitted, 'submitted_by' => ($actor ?? Auth::user())?->getKey(), 'submitted_at' => now()]);

            return $count;
        });
    }
}
