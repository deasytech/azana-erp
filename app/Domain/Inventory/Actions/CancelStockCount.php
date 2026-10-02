<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\StockCount;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\StockCountStatus;
use Illuminate\Support\Facades\DB;

/** Abandons a count that has not been submitted. */
class CancelStockCount
{
    public function __invoke(StockCount $count): StockCount
    {
        return DB::transaction(function () use ($count) {
            $count = StockCount::lockForUpdate()->findOrFail($count->id);

            if ($count->status !== StockCountStatus::Draft) {
                throw new DomainException('Only a count that has not been submitted can be cancelled.', 'count_not_draft');
            }

            $count->update(['status' => StockCountStatus::Cancelled]);

            return $count;
        });
    }
}
