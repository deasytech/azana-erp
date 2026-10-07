<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Inventory\Actions\IssueStock;
use App\Domain\Production\Actions\RemovePigsFromBatch;
use App\Domain\Sales\Events\SaleConfirmed;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Models\SalesOrderLine;
use App\Domain\Semen\Actions\AssertSemenBatchSellable;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\BatchEventType;
use App\Enums\CreditStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\ReservationStatus;
use App\Enums\SalesLineKind;
use App\Enums\SalesOrderStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Hands over a confirmed order, all or nothing: semen doses leave stock by batch (traceable to the order), sold
 * pigs are marked sold (withdrawals and quarantine are checked again), pigs from a batch come off its head count,
 * and the invoice is issued at the prices on the order. Any deposit the customer holds is applied to it.
 */
class DispatchSalesOrder
{
    public function __construct(
        private readonly NextNumber $nextNumber,
        private readonly IssueStock $issueStock,
        private readonly ChangeAnimalStatus $changeStatus,
        private readonly RemovePigsFromBatch $removePigs,
        private readonly AssertSemenBatchSellable $sellable,
        private readonly AllocateCustomerFunds $allocate,
    ) {}

    public function __invoke(SalesOrder $order, CarbonInterface $dispatchedOn, ?string $dispatchNote = null, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($order, $dispatchedOn, $dispatchNote, $actor) {
            $order = SalesOrder::lockForUpdate()->with(['lines.animal', 'lines.productionBatch', 'lines.meatLine.product'])->findOrFail($order->id);
            $customer = Customer::findOrFail($order->customer_id);

            $this->validate($order, $customer, $dispatchedOn);

            foreach ($order->lines as $line) {
                $this->hand($order, $line, $dispatchedOn, $actor);
            }

            $invoice = $this->invoice($order, $customer, $dispatchedOn, $actor);

            $order->update([
                'status' => SalesOrderStatus::Dispatched, 'dispatched_on' => $dispatchedOn, 'dispatch_note' => $dispatchNote,
                'dispatched_by' => ($actor ?? Auth::user())?->getKey(),
            ]);
            ($this->allocate)($invoice);
            SaleConfirmed::dispatch($invoice);

            return $invoice;
        });
    }

    private function validate(SalesOrder $order, Customer $customer, CarbonInterface $on): void
    {
        $order->status === SalesOrderStatus::Confirmed || throw new DomainException("{$order->number} is {$order->status->label()}: only a confirmed order can be dispatched.", 'order_not_confirmed');
        $customer->credit_status !== CreditStatus::Blocked || throw new DomainException("{$customer->name} is blocked from buying.", 'customer_blocked');

        if ($on->gt(now()->addMinutes(5)) || $on->copy()->startOfDay()->lt($order->ordered_on)) {
            throw new DomainException('The dispatch cannot be in the future or before the order date.', 'dispatch_date');
        }
    }

    /** Takes the goods out and settles the order line's reservation. */
    private function hand(SalesOrder $order, SalesOrderLine $line, CarbonInterface $on, ?User $actor): void
    {
        $line->reservations()->where('status', ReservationStatus::Active)->update(['status' => ReservationStatus::Fulfilled]);
        $reason = "Sold on {$order->number}";

        match ($line->kind) {
            SalesLineKind::Semen => $this->handSemen($order, $line, $on, $reason, $actor),
            SalesLineKind::PigAnimal => ($this->changeStatus)($line->animal, AnimalStatus::Sold, $reason, $on, $actor),
            SalesLineKind::Meat => $this->handMeat($order, $line, $on, $reason, $actor),
            SalesLineKind::PigBatch => ($this->removePigs)($line->productionBatch, BatchEventType::Sale, (int) $line->heads, $on, $reason, $actor, "sale:{$order->number}:{$line->id}"),
        };
    }

    private function handSemen(SalesOrder $order, SalesOrderLine $line, CarbonInterface $on, string $reason, ?User $actor): void
    {
        $batch = ($this->sellable)($line->semen_batch_id);

        ($this->issueStock)(InventoryTransactionType::Sale, $batch->inventoryBatch->inventory_item_id, $line->inventory_location_id, (string) (int) $line->quantity, $on, [
            'batch' => $batch->inventory_batch_id, 'source_type' => 'sales_order', 'source_id' => $order->id, 'reason' => $reason,
        ], $actor);
    }

    private function handMeat(SalesOrder $order, SalesOrderLine $line, CarbonInterface $on, string $reason, ?User $actor): void
    {
        $lot = $line->meatLine;

        ($this->issueStock)(InventoryTransactionType::Sale, $lot->product->inventory_item_id, $line->inventory_location_id, (string) $line->quantity, $on, [
            'batch' => $lot->inventory_batch_id, 'source_type' => 'sales_order', 'source_id' => $order->id, 'reason' => $reason,
        ], $actor);
    }

    private function invoice(SalesOrder $order, Customer $customer, CarbonInterface $on, ?User $actor): Invoice
    {
        $invoice = Invoice::create([
            'number' => sprintf('INV-%06d', ($this->nextNumber)('invoice')),
            'customer_id' => $customer->id,
            'sales_order_id' => $order->id,
            'issued_on' => $on,
            'due_on' => $on->copy()->startOfDay()->addDays($customer->payment_terms_days),
            'currency_code' => $order->currency_code,
            'total_minor' => $order->total_minor,
            'created_by' => ($actor ?? Auth::user())?->getKey(),
        ]);

        foreach ($order->lines as $line) {
            $invoice->lines()->create([
                'sales_order_line_id' => $line->id, 'kind' => $line->kind, 'description' => $line->description, 'unit' => $line->unit,
                'quantity' => $line->quantity, 'unit_price_minor' => $line->unit_price_minor, 'discount_percent' => $line->discount_percent,
                'line_total_minor' => $line->line_total_minor, 'semen_batch_id' => $line->semen_batch_id,
                'animal_id' => $line->animal_id, 'production_batch_id' => $line->production_batch_id, 'meat_production_line_id' => $line->meat_production_line_id,
            ]);
        }

        return $invoice;
    }
}
