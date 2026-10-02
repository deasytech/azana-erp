<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Inventory\Actions\ReverseInventoryTransaction;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Voids a feed record; any stock it drew out of a store is put back (reversed in the ledger) with it. */
class VoidFeedConsumption
{
    public function __construct(private readonly ReverseInventoryTransaction $reverse) {}

    public function __invoke(FeedConsumptionRecord $record, string $reason, ?User $actor = null): FeedConsumptionRecord
    {
        if ($record->isVoided()) {
            throw new DomainException('This feed record is already voided.', 'already_voided');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void a feed record.', 'reason_required');
        }

        return DB::transaction(function () use ($record, $reason, $actor) {
            if ($record->inventory_group) {
                ($this->reverse)->group($record->inventory_group, 'Feed record voided: '.trim($reason), $actor);
            }

            $record->forceFill(['voided_at' => now(), 'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason)])->save();

            return $record;
        });
    }
}
