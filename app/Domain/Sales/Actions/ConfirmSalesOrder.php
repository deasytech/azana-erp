<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Health\Actions\AssertAnimalCanEnterFoodChain;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Services\StockValuation;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Models\SalesOrderLine;
use App\Domain\Sales\Models\StockReservation;
use App\Domain\Semen\Actions\AssertSemenBatchSellable;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ReservationStatus;
use App\Enums\SalesLineKind;
use App\Enums\SalesOrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a draft order: checks the customer's credit against the farm's rule, requires approval for a discount
 * above the configured limit, and reserves the semen doses, pigs or batch heads so nothing is sold twice.
 */
class ConfirmSalesOrder
{
    public function __construct(
        private readonly CheckCustomerCredit $credit,
        private readonly AssertSemenBatchSellable $sellable,
        private readonly AssertAnimalCanEnterFoodChain $foodChain,
        private readonly StockValuation $stock,
        private readonly ResolveSettings $settings,
    ) {}

    public function __invoke(SalesOrder $order, User $confirmer): SalesOrder
    {
        return DB::transaction(function () use ($order, $confirmer) {
            $order = SalesOrder::lockForUpdate()->with(['lines.semenBatch', 'lines.animal', 'lines.productionBatch'])->findOrFail($order->id);
            $customer = Customer::lockForUpdate()->findOrFail($order->customer_id);

            $order->status === SalesOrderStatus::Draft || throw new DomainException("{$order->number} is {$order->status->label()}: only a draft order can be confirmed.", 'order_not_draft');
            $customer->is_active || throw new DomainException("{$customer->name} is not an active customer.", 'inactive_customer');

            $this->assertDiscountAllowed($order, $confirmer);
            $warning = ($this->credit)($customer, $order->total_minor, $order->currency_code);

            foreach ($order->lines as $line) {
                $this->reserve($order, $line);
            }

            $order->update(['status' => SalesOrderStatus::Confirmed, 'confirmed_by' => $confirmer->id, 'confirmed_at' => now(), 'credit_warning' => $warning]);

            return $order;
        });
    }

    private function assertDiscountAllowed(SalesOrder $order, User $confirmer): void
    {
        $limit = (string) $this->settings->get('sales.discount_approval_threshold_percent');
        $largest = $order->lines->reduce(fn (string $max, SalesOrderLine $l) => bccomp((string) $l->discount_percent, $max, 2) > 0 ? (string) $l->discount_percent : $max, '0');

        if (bccomp($largest, $limit, 2) > 0 && ! ($confirmer->is_active && $confirmer->can('sales.approve'))) {
            throw new DomainException("A discount of {$largest}% is above the {$limit}% limit: someone who can approve must confirm this order.", 'discount_needs_approval');
        }
    }

    private function reserve(SalesOrder $order, SalesOrderLine $line): void
    {
        match ($line->kind) {
            SalesLineKind::Semen => $this->reserveSemen($line),
            SalesLineKind::PigAnimal => $this->reserveAnimal($order, $line),
            SalesLineKind::PigBatch => $this->reserveBatchPigs($line),
        };
    }

    private function reserveSemen(SalesOrderLine $line): void
    {
        $batch = ($this->sellable)($line->semen_batch_id);
        $item = InventoryItem::findOrFail($batch->inventoryBatch->inventory_item_id);

        $held = (string) StockReservation::where('inventory_batch_id', $batch->inventory_batch_id)->where('inventory_location_id', $line->inventory_location_id)
            ->where('status', ReservationStatus::Active)->sum('quantity');
        $free = bcsub($this->stock->onHand($item->id, $line->inventory_location_id, $batch->inventory_batch_id), $held, 3);

        if (bccomp($free, (string) $line->quantity, 3) < 0) {
            throw new DomainException("{$batch->number}: only ".(int) $free.' doses are free in that store (the rest are on hand but reserved for other orders), '.(int) $line->quantity.' needed.', 'insufficient_stock');
        }

        StockReservation::create([
            'sales_order_line_id' => $line->id, 'inventory_batch_id' => $batch->inventory_batch_id,
            'inventory_location_id' => $line->inventory_location_id, 'quantity' => $line->quantity,
        ]);
    }

    private function reserveAnimal(SalesOrder $order, SalesOrderLine $line): void
    {
        $animal = $line->animal;

        $animal->isActive() || throw new DomainException("{$animal->animal_number} is {$animal->status->label()} and cannot be sold.", 'animal_not_active');
        ($this->foodChain)($animal, $order->ordered_on);

        if (StockReservation::where('animal_id', $animal->id)->where('status', ReservationStatus::Active)->exists()) {
            throw new DomainException("{$animal->animal_number} is already reserved for another order.", 'animal_reserved');
        }

        StockReservation::create(['sales_order_line_id' => $line->id, 'animal_id' => $animal->id, 'quantity' => 1]);
    }

    private function reserveBatchPigs(SalesOrderLine $line): void
    {
        $batch = $line->productionBatch;

        $batch->isActive() || throw new DomainException("{$batch->code} is closed.", 'batch_closed');
        $held = (int) StockReservation::where('production_batch_id', $batch->id)->where('status', ReservationStatus::Active)->sum('quantity');
        $free = $batch->headCount() - $held;

        if ($free < $line->heads) {
            throw new DomainException("{$batch->code}: only {$free} pigs are free ({$held} are reserved for other orders), {$line->heads} needed.", 'insufficient_pigs');
        }

        StockReservation::create(['sales_order_line_id' => $line->id, 'production_batch_id' => $batch->id, 'quantity' => $line->heads]);
    }
}
