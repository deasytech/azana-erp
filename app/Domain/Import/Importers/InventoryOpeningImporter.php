<?php

namespace App\Domain\Import\Importers;

use App\Domain\Import\Support\ImportColumn;
use App\Domain\Import\Support\Importer;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Supplier\Models\Supplier;
use App\Enums\InventoryTransactionType;
use App\Models\User;

/**
 * What is in the stores on the day you start. Every line becomes an "opening" ledger entry through ReceiveStock, so
 * these balances are in the ledger like any stock, with their cost layers. An opening balance can be loaded once per item,
 * store and batch: a second load is reported, and later corrections go through a stock count or adjustment.
 */
class InventoryOpeningImporter extends Importer
{
    public function __construct(private readonly ReceiveStock $receive) {}

    public function key(): string
    {
        return 'inventory_opening';
    }

    public function label(): string
    {
        return 'Inventory opening balances';
    }

    public function description(): string
    {
        return 'Stock in the stores on the day you go live, one line per item, store and batch. The items and stores must already be set up. An opening balance can be loaded only once for the same item, store and batch.';
    }

    public function module(): string
    {
        return 'inventory';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('item', 'A stock item (its code or name).', true, 'ITM-0004'),
            new ImportColumn('store', 'The store, silo or cold room holding it (its code or name).', true, 'STORE-0001'),
            new ImportColumn('quantity', 'How much is there, in the item\'s unit (up to 3 decimals).', true, '1250.5'),
            new ImportColumn('unit_cost', 'What one unit cost, in naira (2 decimals).', true, '420.00'),
            new ImportColumn('as_of', 'YYYY-MM-DD the count was taken (not in the future).', true, '2026-10-01'),
            new ImportColumn('batch_number', 'The supplier or lot number. Required for items tracked by batch.', false, 'LOT-8841'),
            new ImportColumn('expiry_date', 'YYYY-MM-DD. Required for items that expire.'),
            new ImportColumn('supplier', 'The supplier (code or name), if known.'),
        ];
    }

    public function save(array $rows, ?User $actor): void
    {
        $row = $rows[0];
        $item = $this->find(InventoryItem::class, $row, 'item', required: true, label: 'stock item');
        $store = $this->find(InventoryLocation::class, $row, 'store', required: true, label: 'store');
        $supplier = $this->find(Supplier::class, $row, 'supplier', label: 'supplier');
        $quantity = $this->decimal($row, 'quantity', 3, true);
        $cost = $this->minor($row, 'unit_cost', true);
        $on = $this->date($row, 'as_of', true);
        $expiry = $this->date($row, 'expiry_date');
        $batch = $row['batch_number'] ?? null;

        bccomp($quantity, '0', 3) > 0 || throw $this->problem('quantity', 'must be more than 0');
        $on->isFuture() && throw $this->problem('as_of', 'cannot be in the future');

        $exists = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::Opening->value)
            ->where('inventory_item_id', $item->id)->where('inventory_location_id', $store->id)
            ->when($batch, fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('batch_number', $batch)), fn ($q) => $q->whereNull('inventory_batch_id'))
            ->exists();

        $exists && throw $this->problem('item', "already has an opening balance in {$store->name}".($batch ? " for batch {$batch}" : '').'. Use a stock count or adjustment to correct it');

        ($this->receive)(InventoryTransactionType::Opening, $item, $store, $quantity, $on, [
            'unit_cost_minor' => $cost, 'reason' => 'Opening balance (import)', 'batch_number' => $batch,
            'expiry_date' => $expiry?->toDateString(), 'supplier_id' => $supplier?->id,
        ], $actor);
    }
}
