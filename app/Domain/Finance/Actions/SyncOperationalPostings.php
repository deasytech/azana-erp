<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Procurement\Models\SupplierPayment;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\JournalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptMethod;
use App\Enums\SalesLineKind;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Carries the operational documents into the ledger, once each (the source key stops a repeat):
 *  - sales invoice:   debit receivables; credit sales (pigs / semen / meat), by cost centre
 *  - customer payment: debit cash or bank; credit receivables (a payment on deposit leaves receivables in credit)
 *  - supplier invoice: debit purchases; credit payables
 *  - supplier payment: debit payables; credit cash or bank
 * A voided payment or invoice that was already posted gets a reversing entry. Documents dated in a closed period are skipped and reported.
 */
class SyncOperationalPostings
{
    /** Cost centre (code) that earns each kind of sale. */
    private const SALES_CENTRE = ['semen' => 'SEM', 'meat' => 'MEA', 'pig_animal' => 'FIN', 'pig_batch' => 'FIN'];

    public function __construct(private readonly PostJournal $post, private readonly ReverseJournal $reverse) {}

    /** @return array{posted: int, reversed: int, skipped: list<string>} */
    public function __invoke(): array
    {
        // One run at a time across the scheduler and the Journal page (the cache store must support locks: database, redis...).
        try {
            return Cache::lock('finance:sync-operational-postings', 300)->block(30, fn () => $this->sync());
        } catch (LockTimeoutException) {
            throw new DomainException('Another posting run is in progress; try again in a moment.', 'sync_busy');
        }
    }

    /** @return array{posted: int, reversed: int, skipped: list<string>} */
    private function sync(): array
    {
        $result = ['posted' => 0, 'reversed' => 0, 'skipped' => []];

        foreach ($this->documents() as [$key, $date, $description, $lines, $voided]) {
            try {
                $this->carry($key, $date, $description, $lines, $voided, $result);
            } catch (DomainException $e) {
                $result['skipped'][] = "{$description}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    /** @param array{posted: int, reversed: int, skipped: list<string>} $result */
    private function carry(string $key, CarbonInterface $date, string $description, array $lines, bool $voided, array &$result): void
    {
        $entry = JournalEntry::firstWhere('source_key', $key);

        if (! $entry && ! $voided) {
            ($this->post)($date, $description, $lines, JournalStatus::Posted, $key);
            $result['posted']++;
        } elseif ($entry && $voided && ! $entry->reversal()->exists()) {
            ($this->reverse)($entry, 'document voided');
            $result['reversed']++;
        }
    }

    /** @return iterable<array{0: string, 1: CarbonInterface, 2: string, 3: list<array<string, mixed>>, 4: bool}> */
    private function documents(): iterable
    {
        $account = fn (string $key) => Account::system($key)->id;
        $centre = fn (string $code) => CostCentre::where('code', $code)->value('id');

        foreach (Invoice::with('lines')->orderBy('id')->get() as $invoice) {
            $credits = $invoice->lines->groupBy(fn ($l) => $l->kind->value)->map(fn ($lines, $kind) => [
                'account_id' => $account($kind === SalesLineKind::Semen->value ? 'sales_semen' : ($kind === SalesLineKind::Meat->value ? 'sales_meat' : 'sales_pigs')),
                'cost_centre_id' => $centre(self::SALES_CENTRE[$kind]), 'credit_minor' => (int) $lines->sum('line_total_minor'),
            ])->values()->filter(fn ($l) => $l['credit_minor'] > 0)->all();

            if ($invoice->total_minor > 0) {
                yield ["invoice:{$invoice->id}", $invoice->issued_on, "Invoice {$invoice->number}", [['account_id' => $account('receivables'), 'debit_minor' => $invoice->total_minor], ...$credits], false];
            }
        }

        foreach (Payment::orderBy('id')->get() as $payment) {
            $cash = $account($payment->method === ReceiptMethod::Cash ? 'cash' : 'bank');
            $lines = [['account_id' => $cash, 'debit_minor' => $payment->amount_minor], ['account_id' => $account('receivables'), 'credit_minor' => $payment->amount_minor]];

            yield ["payment:{$payment->id}", $payment->received_on, "Receipt {$payment->number}", $lines, $payment->isVoided()];
        }

        foreach (SupplierInvoice::orderBy('id')->get() as $invoice) {
            $lines = [['account_id' => $account('purchases'), 'debit_minor' => $invoice->total_minor], ['account_id' => $account('payables'), 'credit_minor' => $invoice->total_minor]];

            $invoice->total_minor > 0 && yield ["supplier-invoice:{$invoice->id}", $invoice->invoice_date, "Supplier invoice {$invoice->number}", $lines, $invoice->isVoided()];
        }

        foreach (SupplierPayment::whereIn('status', [PaymentStatus::Paid, PaymentStatus::Voided])->orderBy('id')->get() as $payment) {
            $cash = $account($payment->method === PaymentMethod::Cash ? 'cash' : 'bank');
            $lines = [['account_id' => $account('payables'), 'debit_minor' => $payment->amount_minor], ['account_id' => $cash, 'credit_minor' => $payment->amount_minor]];

            yield ["supplier-payment:{$payment->id}", $payment->paid_on, "Supplier payment {$payment->number}", $lines, $payment->status === PaymentStatus::Voided];
        }
    }
}
