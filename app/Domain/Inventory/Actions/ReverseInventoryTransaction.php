<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Events\InventoryTransactionPosted;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Inventory\Services\StockValuation;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a ledger line by adding the opposite line (the original is never touched). Stock that came in can be
 * reversed only while all of it is still there; stock that went out comes back at the value it left with.
 */
class ReverseInventoryTransaction
{
    public function __construct(private readonly StockValuation $valuation) {}

    public function __invoke(InventoryTransaction $transaction, string $reason, ?User $actor = null): InventoryTransaction
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to reverse a stock transaction.', 'reason_required');
        }

        return DB::transaction(function () use ($transaction, $reason, $actor) {
            InventoryItem::lockForUpdate()->findOrFail($transaction->inventory_item_id);
            $original = InventoryTransaction::findOrFail($transaction->id);

            if ($original->reverses_id !== null) {
                throw new DomainException('A reversal cannot itself be reversed; post a new transaction instead.', 'reversal_of_reversal');
            }

            if (InventoryTransaction::where('reverses_id', $original->id)->exists()) {
                throw new DomainException('This transaction has already been reversed.', 'already_reversed');
            }

            $inbound = $original->isInbound();

            if ($inbound) {
                $this->valuation->removeReceiptLayer($original);
            }

            $reversal = InventoryTransaction::create([
                'group_uuid' => $original->group_uuid,
                'type' => $original->type,
                'inventory_item_id' => $original->inventory_item_id,
                'inventory_location_id' => $original->inventory_location_id,
                'inventory_batch_id' => $original->inventory_batch_id,
                'quantity' => bcmul((string) $original->quantity, '-1', 3),
                'value_minor' => -$original->value_minor,
                'occurred_on' => now()->startOfDay(),
                'source_type' => $original->source_type,
                'source_id' => $original->source_id,
                'reverses_id' => $original->id,
                'reason' => trim($reason),
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);

            if (! $inbound) {
                $this->valuation->addLayer($reversal, now()->startOfDay());
            }

            InventoryTransactionPosted::dispatch($reversal);

            return $reversal;
        });
    }

    /**
     * Reverses every line of a group, last first (e.g. the lines of one issue or one goods receipt).
     *
     * @return Collection<int, InventoryTransaction>
     */
    public function group(string $groupUuid, string $reason, ?User $actor = null): Collection
    {
        return DB::transaction(fn () => InventoryTransaction::where('group_uuid', $groupUuid)->whereNull('reverses_id')->doesntHave('reversal')
            ->orderByDesc('id')->get()->map(fn (InventoryTransaction $row) => ($this)($row, $reason, $actor)));
    }
}
