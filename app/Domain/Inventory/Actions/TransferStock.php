<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Moves stock between two stores at the cost it left with. Both legs are ledger lines sharing one group id. */
class TransferStock
{
    public function __construct(private readonly IssueStock $issue, private readonly PostInventoryTransaction $post) {}

    /**
     * @param  array<string, mixed>  $details  batch, reason, idempotency_key
     * @return Collection<int, InventoryTransaction> the transfer-out lines followed by the transfer-in lines
     */
    public function __invoke(InventoryItem|int $item, InventoryLocation|int $from, InventoryLocation|int $to, string $quantity, CarbonInterface $on, array $details = [], ?User $actor = null): Collection
    {
        if ((($from instanceof InventoryLocation) ? $from->id : $from) === (($to instanceof InventoryLocation) ? $to->id : $to)) {
            throw new DomainException('Choose two different stores.', 'transfer_same_location');
        }

        $key = $details['idempotency_key'] ?? null;

        if ($key && ($replay = InventoryTransaction::where('idempotency_key', $key)->orWhere('idempotency_key', 'like', $key.'#%')->orWhere('idempotency_key', 'like', $key.'/in%')->orderBy('id')->get())->isNotEmpty()) {
            return $replay;
        }

        return DB::transaction(function () use ($item, $from, $to, $quantity, $on, $details, $actor, $key) {
            $group = (string) Str::uuid();
            $out = ($this->issue)(InventoryTransactionType::TransferOut, $item, $from, $quantity, $on, ['group_uuid' => $group, 'idempotency_key' => $key] + $details, $actor);

            $in = $out->values()->map(fn (InventoryTransaction $leg, int $n) => ($this->post)(
                InventoryTransactionType::TransferIn, $item, $to, ltrim((string) $leg->quantity, '-'), $on,
                ['batch' => $leg->inventory_batch_id, 'value_minor' => -$leg->value_minor, 'group_uuid' => $group, 'reason' => $details['reason'] ?? null,
                    'idempotency_key' => $key ? $key.'/in'.($n + 1) : null],
                $actor,
            ));

            return $out->concat($in);
        });
    }
}
