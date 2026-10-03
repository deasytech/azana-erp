<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Procurement\Models\GoodsReceipt;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Receives a delivery against an approved order: every line is added to stock through the inventory ledger
 * (creating the batch, with the supplier and expiry) at the ordered unit cost. A delivery may not exceed
 * what is still due, plus the configured tolerance (`procurement.over_receipt_tolerance_percent`).
 * Each line: purchase_order_line_id, quantity, batch_number and expiry_date (for batch-tracked items).
 */
class ReceiveGoods
{
    public function __construct(
        private readonly NextNumber $nextNumber,
        private readonly ReceiveStock $receiveStock,
        private readonly SyncPurchaseOrderReceiptStatus $sync,
        private readonly ResolveSettings $settings,
    ) {}

    /** @param list<array<string, mixed>> $lines */
    public function __invoke(PurchaseOrder $order, InventoryLocation|int $location, CarbonInterface $receivedOn, array $lines, ?string $deliveryNote = null, ?string $notes = null, ?User $actor = null): GoodsReceipt
    {
        return DB::transaction(function () use ($order, $location, $receivedOn, $lines, $deliveryNote, $notes, $actor) {
            $order = PurchaseOrder::lockForUpdate()->with('lines')->findOrFail($order->id);
            $location = InventoryLocation::findOrFail($location instanceof InventoryLocation ? $location->id : $location);

            $this->validate($order, $location, $receivedOn, $lines);

            $receipt = GoodsReceipt::create([
                'number' => sprintf('GR-%06d', ($this->nextNumber)('goods_receipt')),
                'purchase_order_id' => $order->id,
                'inventory_location_id' => $location->id,
                'received_on' => $receivedOn,
                'delivery_note' => $deliveryNote,
                'notes' => $notes,
                'received_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            foreach ($lines as $line) {
                $orderLine = $order->lines->firstWhere('id', (int) $line['purchase_order_line_id']);

                $stock = ($this->receiveStock)(InventoryTransactionType::Receipt, $orderLine->inventory_item_id, $location, $line['quantity'], $receivedOn, [
                    'unit_cost_minor' => $orderLine->unit_cost_minor,
                    'batch_number' => $line['batch_number'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'supplier_id' => $order->supplier_id,
                    'source_type' => 'goods_receipt',
                    'source_id' => $receipt->id,
                    'reason' => "Received on {$order->number} ({$receipt->number})",
                ], $actor);

                $receipt->lines()->create([
                    'purchase_order_line_id' => $orderLine->id,
                    'inventory_item_id' => $orderLine->inventory_item_id,
                    'quantity' => $line['quantity'],
                    'unit_cost_minor' => $orderLine->unit_cost_minor,
                    'value_minor' => $stock->value_minor,
                    'inventory_batch_id' => $stock->inventory_batch_id,
                    'inventory_transaction_id' => $stock->id,
                ]);
            }

            ($this->sync)($order);

            return $receipt->load('lines');
        });
    }

    /** @param list<array<string, mixed>> $lines */
    private function validate(PurchaseOrder $order, InventoryLocation $location, CarbonInterface $on, array $lines): void
    {
        if (! $order->status->canReceive()) {
            throw new DomainException("{$order->number} is {$order->status->label()}: goods can only be received against an approved order.", 'order_not_receivable');
        }

        if (! $location->is_active) {
            throw new DomainException('Choose an active store.', 'inactive_stock_target');
        }

        if ($on->gt(now()->addMinutes(5)) || $on->lt($order->ordered_on)) {
            throw new DomainException('The delivery cannot be dated in the future or before the order.', 'receipt_date');
        }

        if ($lines === []) {
            throw new DomainException('Enter the quantity received for at least one line.', 'lines_required');
        }

        $tolerance = bcadd('1', bcdiv((string) $this->settings->get('procurement.over_receipt_tolerance_percent'), '100', 6), 6);
        $seen = [];

        foreach ($lines as $line) {
            $orderLine = $order->lines->firstWhere('id', (int) ($line['purchase_order_line_id'] ?? 0))
                ?? throw new DomainException('A received line does not belong to this order.', 'invalid_order_line');
            $quantity = (string) ($line['quantity'] ?? '');

            if (isset($seen[$orderLine->id])) {
                throw new DomainException('Each order line may appear only once per delivery.', 'duplicate_line');
            }

            $seen[$orderLine->id] = true;

            if (! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $quantity) || bccomp($quantity, '0', 3) <= 0) {
                throw new DomainException('Each received quantity must be a positive number with at most 3 decimals.', 'line_quantity');
            }

            $allowed = bcmul((string) $orderLine->quantity, $tolerance, 3);

            if (bccomp(bcadd($orderLine->receivedQuantity(), $quantity, 3), $allowed, 3) > 0) {
                throw new DomainException("{$orderLine->item->name}: more than the order allows (ordered {$orderLine->quantity}, already received {$orderLine->receivedQuantity()}).", 'over_receipt');
            }
        }
    }
}
