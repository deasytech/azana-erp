<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Events\PaymentReceived;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\PaymentAllocation;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ReceiptMethod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records money received from a customer and settles invoices with it: the invoices named, or the oldest first when
 * none are named. A part payment simply settles part of an invoice; anything left over stays with the customer as a
 * deposit for later invoices.
 */
class RecordCustomerPayment
{
    public function __construct(private readonly NextNumber $nextNumber) {}

    /** @param array<int, int>|null $allocations minor units to put against each invoice, keyed by invoice id */
    public function __invoke(Customer|int $customer, int $amountMinor, ReceiptMethod $method, CarbonInterface $receivedOn, ?string $reference = null, ?array $allocations = null, ?string $notes = null, ?User $actor = null, ?string $idempotencyKey = null): Payment
    {
        if ($amountMinor <= 0) {
            throw new DomainException('The payment must be a positive amount.', 'payment_amount');
        }

        if ($receivedOn->gt(now()->addMinutes(5))) {
            throw new DomainException('A payment cannot be dated in the future.', 'payment_date');
        }

        return DB::transaction(function () use ($customer, $amountMinor, $method, $receivedOn, $reference, $allocations, $notes, $actor, $idempotencyKey) {
            $customer = Customer::lockForUpdate()->findOrFail($customer instanceof Customer ? $customer->id : $customer);

            if ($idempotencyKey && ($existing = Payment::firstWhere('idempotency_key', $idempotencyKey))) {
                return $existing->customer_id === $customer->id && $existing->amount_minor === $amountMinor ? $existing : throw new DomainException('This idempotency key was already used for a different payment.', 'idempotency_conflict');
            }

            $invoices = Invoice::where('customer_id', $customer->id)->with('allocations.payment')->orderBy('issued_on')->orderBy('id')->get()->filter(fn (Invoice $i) => $i->balanceMinor() > 0);
            $plan = $allocations === null ? $this->oldestFirst($invoices, $amountMinor) : $this->named($invoices, $allocations, $amountMinor);

            $payment = Payment::create([
                'number' => sprintf('RCT-%06d', ($this->nextNumber)('customer_payment')),
                'customer_id' => $customer->id, 'amount_minor' => $amountMinor, 'method' => $method, 'received_on' => $receivedOn,
                'reference' => $reference, 'notes' => $notes, 'received_by' => ($actor ?? Auth::user())?->getKey(), 'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($plan as $invoiceId => $amount) {
                PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoiceId, 'amount_minor' => $amount]);
            }

            PaymentReceived::dispatch($payment);

            return $payment;
        });
    }

    /** @return array<int, int> */
    private function oldestFirst($invoices, int $amount): array
    {
        $plan = [];

        foreach ($invoices as $invoice) {
            if ($amount <= 0) {
                break;
            }

            $plan[$invoice->id] = $take = min($amount, $invoice->balanceMinor());
            $amount -= $take;
        }

        return $plan;
    }

    /**
     * @param  array<int, int>  $named
     * @return array<int, int>
     */
    private function named($invoices, array $named, int $amount): array
    {
        $named = array_filter($named, fn ($minor) => $minor > 0);

        foreach ($named as $invoiceId => $minor) {
            $invoice = $invoices->firstWhere('id', $invoiceId) ?? throw new DomainException('One of the invoices is not this customer\'s, or is already paid.', 'invalid_invoice');

            if (! is_int($minor) || $minor > $invoice->balanceMinor()) {
                throw new DomainException("{$invoice->number}: that is more than the {$invoice->balanceMinor()} still owed.", 'overpayment');
            }
        }

        if (array_sum($named) > $amount) {
            throw new DomainException('The amounts put against invoices add up to more than was received.', 'allocation_total');
        }

        return $named;
    }
}
