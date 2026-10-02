<?php

namespace App\Domain\Procurement\Concerns;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\System\Exceptions\DomainException;

/** Shared checks for the item lines of purchase requests and orders. */
trait CleansPurchaseLines
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>> the lines with trimmed quantity strings
     */
    private function cleanLines(array $lines): array
    {
        if ($lines === []) {
            throw new DomainException('Add at least one item.', 'lines_required');
        }

        $seen = [];

        return array_map(function (array $line) use (&$seen) {
            $itemId = (int) ($line['inventory_item_id'] ?? 0);
            $quantity = trim((string) ($line['quantity'] ?? ''));

            if (! InventoryItem::where('is_active', true)->whereKey($itemId)->exists()) {
                throw new DomainException('Choose an active stock item for every line.', 'invalid_item');
            }

            if (isset($seen[$itemId])) {
                throw new DomainException('Each item may appear only once; combine the quantities.', 'duplicate_item');
            }

            $seen[$itemId] = true;

            if (! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $quantity) || bccomp($quantity, '0', 3) <= 0) {
                throw new DomainException('Each quantity must be a positive number with at most 3 decimals.', 'line_quantity');
            }

            return ['quantity' => $quantity, 'inventory_item_id' => $itemId] + $line;
        }, array_values($lines));
    }
}
