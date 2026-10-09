<?php

namespace App\Domain\Import\Importers;

use App\Domain\Farm\Models\Farm;
use App\Domain\Import\Support\ImportColumn;
use App\Domain\Import\Support\Importer;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\PaymentAllocation;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\System\Actions\NextNumber;
use App\Enums\ReceiptMethod;
use App\Enums\SalesLineKind;
use App\Enums\SalesOrderStatus;
use App\Models\User;
use App\Support\Ratio;
use Illuminate\Support\Facades\DB;

/**
 * Past sales, kept for history and for what customers still owe. Each row becomes a dispatched order, its invoice and, when
 * something was paid, a receipt - all flagged historical. They move no stock (the goods are long gone) and are not posted to
 * the ledger (opening balances carry the books), but customer balances, sales history and the sales reports include them.
 */
class HistoricalSalesImporter extends Importer
{
    public function __construct(private readonly NextNumber $nextNumber) {}

    public function key(): string
    {
        return 'historical_sales';
    }

    public function label(): string
    {
        return 'Historical sales';
    }

    public function description(): string
    {
        return 'Past sales for the record, one line each. They do not touch stock or the ledger. The same old invoice reference can be loaded only once, so a file cannot be imported twice by mistake.';
    }

    public function module(): string
    {
        return 'sales';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('reference', 'Your old invoice or receipt number. It identifies the sale, so it must be unique.', true, 'OLD-2025-0142'),
            new ImportColumn('customer', 'An existing customer (code or name).', true, 'C-000012'),
            new ImportColumn('sale_date', 'YYYY-MM-DD of the sale.', true, '2025-11-20'),
            new ImportColumn('product', 'pigs, semen or meat.', true, 'pigs'),
            new ImportColumn('description', 'What was sold.', true, '20 finishers'),
            new ImportColumn('unit', 'head, dose or kg.', true, 'head'),
            new ImportColumn('quantity', 'How many units (up to 3 decimals; whole pigs).', true, '20'),
            new ImportColumn('unit_price', 'Price per unit, in naira (2 decimals).', true, '85000.00'),
            new ImportColumn('amount_paid', 'How much of it has been paid, in naira. 0 or blank if nothing yet.', false, '1700000.00'),
            new ImportColumn('payment_method', 'cash, bank_transfer or pos. Required when something was paid.', false, 'bank_transfer'),
            new ImportColumn('paid_on', 'YYYY-MM-DD the money arrived. Blank means the sale date.'),
            new ImportColumn('due_date', 'YYYY-MM-DD payment was due. Blank uses the customer\'s terms.'),
        ];
    }

    public function save(array $rows, ?User $actor): void
    {
        $row = $rows[0];
        $customer = $this->find(Customer::class, $row, 'customer', required: true, label: 'customer');
        $on = $this->date($row, 'sale_date', true);
        $product = $this->choice($row, 'product', ['pigs', 'semen', 'meat'], true);
        $unit = $this->choice($row, 'unit', ['head', 'dose', 'kg'], true);
        $quantity = $this->decimal($row, 'quantity', 3, true);
        $price = $this->minor($row, 'unit_price', true);
        $paid = $this->minor($row, 'amount_paid') ?? 0;
        $method = $this->choice($row, 'payment_method', array_map(fn ($m) => $m->value, ReceiptMethod::cases()));
        $paidOn = $this->date($row, 'paid_on') ?? $on;
        $due = $this->date($row, 'due_date') ?? $on->copy()->addDays($customer->payment_terms_days);

        $on->isFuture() && throw $this->problem('sale_date', 'cannot be in the future');
        bccomp($quantity, '0', 3) > 0 || throw $this->problem('quantity', 'must be more than 0');
        $price > 0 || throw $this->problem('unit_price', 'must be more than 0');
        $product === 'pigs' && ! preg_match('/^\d+$/', $quantity) && throw $this->problem('quantity', 'must be a whole number of pigs');

        $total = Ratio::toWhole(bcmul($quantity, (string) $price, 6));
        $paid <= $total || throw $this->problem('amount_paid', 'is more than the sale came to');
        $paid === 0 || $method !== null || throw $this->problem('payment_method', 'is required when something was paid');
        $paidOn->lt($on) && throw $this->problem('paid_on', 'cannot be before the sale date');
        $paidOn->isFuture() && throw $this->problem('paid_on', 'cannot be in the future');

        $key = 'hist:'.sha1($row['reference']);
        SalesOrder::where('idempotency_key', $key)->exists() && throw $this->problem('reference', "\"{$row['reference']}\" was already imported");

        $kind = match ($product) {
            'semen' => SalesLineKind::Semen, 'meat' => SalesLineKind::Meat, default => SalesLineKind::PigBatch
        };

        DB::transaction(function () use ($row, $customer, $on, $due, $kind, $unit, $quantity, $price, $total, $paid, $method, $paidOn, $key, $actor) {
            $note = "Imported history. Old reference: {$row['reference']}";
            $order = SalesOrder::create([
                'number' => sprintf('SO-%06d', ($this->nextNumber)('sales_order')), 'customer_id' => $customer->id,
                'status' => SalesOrderStatus::Dispatched, 'ordered_on' => $on, 'dispatched_on' => $on, 'currency_code' => Farm::defaultCurrency(),
                'total_minor' => $total, 'notes' => $note, 'created_by' => $actor?->getKey(), 'idempotency_key' => $key, 'is_historical' => true,
            ]);

            $line = $order->lines()->create([
                'kind' => $kind, 'description' => $row['description'], 'unit' => $unit, 'quantity' => $quantity,
                'heads' => $kind === SalesLineKind::PigBatch ? (int) $quantity : null, 'unit_price_minor' => $price, 'discount_percent' => 0, 'line_total_minor' => $total,
            ]);

            $invoice = Invoice::create([
                'number' => sprintf('INV-%06d', ($this->nextNumber)('invoice')), 'customer_id' => $customer->id, 'sales_order_id' => $order->id,
                'issued_on' => $on, 'due_on' => $due, 'currency_code' => $order->currency_code, 'total_minor' => $total,
                'notes' => $note, 'created_by' => $actor?->getKey(), 'is_historical' => true,
            ]);

            $invoice->lines()->create([
                'sales_order_line_id' => $line->id, 'kind' => $kind, 'description' => $row['description'], 'unit' => $unit,
                'quantity' => $quantity, 'unit_price_minor' => $price, 'discount_percent' => 0, 'line_total_minor' => $total,
            ]);

            if ($paid > 0) {
                $payment = Payment::create([
                    'number' => sprintf('RCT-%06d', ($this->nextNumber)('customer_payment')), 'customer_id' => $customer->id, 'amount_minor' => $paid,
                    'method' => $method, 'received_on' => $paidOn, 'reference' => $row['reference'], 'notes' => $note,
                    'received_by' => $actor?->getKey(), 'is_historical' => true,
                ]);

                PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount_minor' => $paid]);
            }
        });
    }
}
