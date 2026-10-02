<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Adds stock (opening balance, purchase, receipt, production or return), creating the batch when the item is
 * tracked by batch. $details are PostInventoryTransaction's plus: batch_number, expiry_date, manufactured_on,
 * supplier_id (used to find or create the batch).
 */
class ReceiveStock
{
    public function __construct(private readonly PostInventoryTransaction $post) {}

    /** @param array<string, mixed> $details */
    public function __invoke(InventoryTransactionType $type, InventoryItem|int $item, InventoryLocation|int $location, string $quantity, CarbonInterface $on, array $details = [], ?User $actor = null): InventoryTransaction
    {
        if (! $type->adds() && $type !== InventoryTransactionType::Return) {
            throw new DomainException('Choose a type that adds stock.', 'stock_direction');
        }

        return DB::transaction(function () use ($type, $item, $location, $quantity, $on, $details, $actor) {
            $item = $item instanceof InventoryItem ? $item : InventoryItem::findOrFail($item);

            if ($item->tracks_batches && empty($details['batch'])) {
                $details['batch'] = $this->batch($item, $on, $details);
            }

            return ($this->post)($type, $item, $location, $quantity, $on, $details, $actor);
        });
    }

    /** @param array<string, mixed> $details */
    private function batch(InventoryItem $item, CarbonInterface $on, array $details): InventoryBatch
    {
        $number = trim((string) ($details['batch_number'] ?? ''));

        if ($number === '') {
            throw new DomainException("{$item->name} is tracked by batch: give the batch number.", 'batch_required');
        }

        $expiry = $details['expiry_date'] ?? null;

        if ($item->tracks_expiry && ! $expiry) {
            throw new DomainException("{$item->name} expires: give the batch's expiry date.", 'expiry_required');
        }

        $batch = InventoryBatch::where('inventory_item_id', $item->id)->where('batch_number', $number)->first();

        if ($batch) {
            if ($expiry && $batch->expiry_date?->toDateString() !== $this->date($expiry)) {
                throw new DomainException("Batch {$number} already exists with a different expiry date.", 'batch_expiry_mismatch');
            }

            return $batch;
        }

        return InventoryBatch::create([
            'inventory_item_id' => $item->id,
            'batch_number' => $number,
            'supplier_id' => $details['supplier_id'] ?? null,
            'manufactured_on' => $details['manufactured_on'] ?? null,
            'expiry_date' => $expiry,
            'received_on' => $on,
        ]);
    }

    private function date(mixed $value): string
    {
        return ($value instanceof CarbonInterface ? $value : now()->parse((string) $value))->toDateString();
    }
}
