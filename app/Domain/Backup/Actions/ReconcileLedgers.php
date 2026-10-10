<?php

namespace App\Domain\Backup\Actions;

use App\Domain\Backup\Data\StatusCheck as C;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Inventory\Models\InventoryLayer;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Mobile\Models\SyncMutation;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Procurement\Models\SupplierPayment;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Enums\JournalStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Do the books agree with themselves? Read-only checks that only a bug, a manual database edit or a failed restore could break:
 * the stock ledger against the cost layers (per item, store and batch), every posted journal entry balanced, the receivables
 * and payables in the ledger against the invoices and payments behind them, and mobile mutations stuck in a failed state.
 * Read by the Backups & monitoring page and by `erp:reconcile`.
 */
class ReconcileLedgers
{
    private const STOCK = 'Stock ledger';

    private const JOURNAL = 'Journal balance';

    private const RECEIVABLES = 'Receivables';

    private const PAYABLES = 'Payables';

    private const SYNC = 'Mobile sync';

    /** @return list<C> */
    public function __invoke(): array
    {
        return [$this->stock(), $this->journals(), $this->receivables(), $this->payables(), $this->sync()];
    }

    private function stock(): C
    {
        $ledger = InventoryTransaction::query()
            ->selectRaw('inventory_item_id, inventory_location_id, inventory_batch_id, SUM(quantity) as qty, SUM(value_minor) as value')
            ->groupBy('inventory_item_id', 'inventory_location_id', 'inventory_batch_id')->get()
            ->mapWithKeys(fn ($r) => [$this->key($r) => [$this->quantity($r->qty), (int) $r->value]]);

        $layers = InventoryLayer::query()
            ->selectRaw('inventory_item_id, inventory_location_id, inventory_batch_id, SUM(remaining_quantity) as qty, SUM(remaining_value_minor) as value')
            ->groupBy('inventory_item_id', 'inventory_location_id', 'inventory_batch_id')->get()
            ->mapWithKeys(fn ($r) => [$this->key($r) => [$this->quantity($r->qty), (int) $r->value]]);

        $differences = $ledger->keys()->merge($layers->keys())->unique()->filter(
            fn (string $k) => ($ledger[$k] ?? ['0.000', 0]) !== ($layers[$k] ?? ['0.000', 0])
        );
        $negative = InventoryLayer::where('remaining_quantity', '<', 0)->orWhere('remaining_quantity', '>', DB::raw('quantity'))->count();

        return match (true) {
            $differences->isNotEmpty() => new C(self::STOCK, C::FAILED, $differences->count().' item/store/batch combination(s) where the stock on hand differs from the sum of the ledger.'),
            $negative > 0 => new C(self::STOCK, C::FAILED, "{$negative} cost layer(s) hold a negative or impossible quantity."),
            default => new C(self::STOCK, C::OK, 'Stock on hand and its value agree with the ledger ('.$ledger->count().' item/store/batch rows).'),
        };
    }

    /** A summed quantity to three decimals; SQLite sums decimals as floats, which can come back as 1.0E-13 instead of 0. */
    private function quantity(mixed $sum): string
    {
        return bcadd(is_float($sum) ? sprintf('%.3F', $sum) : (string) $sum, '0', 3);
    }

    private function key(object $row): string
    {
        return "{$row->inventory_item_id}:{$row->inventory_location_id}:{$row->inventory_batch_id}";
    }

    private function journals(): C
    {
        $unbalanced = DB::table('journal_entries')
            ->join('journal_lines', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->groupBy('journal_entries.id', 'journal_entries.total_minor')
            ->havingRaw('SUM(journal_lines.debit_minor) <> SUM(journal_lines.credit_minor) OR SUM(journal_lines.debit_minor) <> journal_entries.total_minor')
            ->pluck('journal_entries.id')->count();

        $empty = JournalEntry::where('status', JournalStatus::Posted->value)->whereDoesntHave('lines')->count();

        return match (true) {
            $unbalanced > 0 => new C(self::JOURNAL, C::FAILED, "{$unbalanced} posted journal entr".($unbalanced === 1 ? 'y does' : 'ies do').' not balance (debits differ from credits).'),
            $empty > 0 => new C(self::JOURNAL, C::FAILED, "{$empty} posted journal entr".($empty === 1 ? 'y has' : 'ies have').' no lines.'),
            default => new C(self::JOURNAL, C::OK, 'Every posted journal entry balances; total debits equal total credits.'),
        };
    }

    /** The receivables account must equal what the posted invoices and receipts say customers owe. */
    private function receivables(): C
    {
        $invoices = fn () => Invoice::where('is_historical', false);
        $payments = fn () => Payment::where('is_historical', false)->whereNull('voided_at');

        $invoiced = (int) $this->withEntry($invoices(), 'invoice', 'invoices.id')->sum('total_minor');
        $received = (int) $this->withEntry($payments(), 'payment', 'payments.id')->sum('amount_minor');
        $unposted = $this->withEntry($invoices(), 'invoice', 'invoices.id', false)->count() + $this->withEntry($payments(), 'payment', 'payments.id', false)->count();

        return $this->compare(self::RECEIVABLES, $this->accountMovement('receivables', ['invoice:%', 'payment:%']), $invoiced - $received, $unposted, 'invoices and receipts');
    }

    private function payables(): C
    {
        $invoices = fn () => SupplierInvoice::whereNull('voided_at');
        $payments = fn () => SupplierPayment::where('status', PaymentStatus::Paid);

        $invoiced = (int) $this->withEntry($invoices(), 'supplier-invoice', 'supplier_invoices.id')->sum('total_minor');
        $paid = (int) $this->withEntry($payments(), 'supplier-payment', 'supplier_payments.id')->sum('amount_minor');
        $unposted = $this->withEntry($invoices(), 'supplier-invoice', 'supplier_invoices.id', false)->count() + $this->withEntry($payments(), 'supplier-payment', 'supplier_payments.id', false)->count();

        return $this->compare(self::PAYABLES, $this->accountMovement('payables', ['supplier-invoice:%', 'supplier-payment:%'], creditNormal: true), $invoiced - $paid, $unposted, 'supplier invoices and payments');
    }

    /**
     * Keeps the documents that do (or, with $posted false, do not) have a journal entry, decided inside the database: an entry made from
     * a document carries the source key "kind:id", so the check never loads every key into PHP.
     *
     * @param  Builder<*>  $documents
     * @return Builder<*>
     */
    private function withEntry(Builder $documents, string $kind, string $idColumn, bool $posted = true): Builder
    {
        $key = DB::getDriverName() === 'sqlite' ? "'{$kind}:' || {$idColumn}" : "CONCAT('{$kind}:', {$idColumn})";

        return $documents->whereRaw(($posted ? '' : 'not ')."exists (select 1 from journal_entries where journal_entries.source_key = {$key})");
    }

    private function compare(string $name, int $ledger, int $documents, int $unposted, string $what): C
    {
        $note = $unposted > 0 ? " {$unposted} document(s) are still waiting to be posted (the hourly posting run, or Finance > Journal)." : '';

        if ($ledger !== $documents) {
            return new C($name, C::FAILED, 'The ledger shows '.number_format($ledger / 100, 2).' but the posted '.$what.' come to '.number_format($documents / 100, 2).'.'.$note);
        }

        $state = $unposted > 0 ? C::WARNING : C::OK;

        return new C($name, $state, "The ledger agrees with the posted {$what}.{$note}");
    }

    /** Net movement on a system account from entries made out of operational documents (a reversal nets the original off). @param list<string> $prefixes */
    private function accountMovement(string $systemKey, array $prefixes, bool $creditNormal = false): int
    {
        $account = DB::table('accounts')->where('system_key', $systemKey)->value('id');

        if (! $account) {
            return 0;
        }

        // Entries made out of documents, and the reversals of those (a reversal has no source key of its own; it nets the entry it reverses).
        $sources = fn () => DB::table('journal_entries')->select('id')->where('status', JournalStatus::Posted->value)->where(function ($q) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                $q->orWhere('source_key', 'like', $prefix);
            }
        });
        $reversals = DB::table('journal_entries')->select('id')->where('status', JournalStatus::Posted->value)->whereIn('reverses_id', $sources());

        // Two sums, subtracted afterwards: the columns are unsigned, and subtracting them row by row overflows on MySQL. Plain SQL on every driver.
        $total = (int) DB::table('journal_lines')->where('account_id', $account)
            ->where(fn ($q) => $q->whereIn('journal_entry_id', $sources())->orWhereIn('journal_entry_id', $reversals))
            ->selectRaw('COALESCE(SUM(debit_minor), 0) - COALESCE(SUM(credit_minor), 0) as net')->value('net');

        return $creditNormal ? -$total : $total;
    }

    private function sync(): C
    {
        $failed = SyncMutation::where('status', SyncMutation::FAILED)->where('attempted_at', '<', now()->subDay())->count();
        $review = SyncMutation::where('status', SyncMutation::CONFLICT)->whereNull('reviewed_at')->count();

        return match (true) {
            $failed > 0 => new C(self::SYNC, C::WARNING, "{$failed} mobile change(s) have failed for over a day and were never accepted; the devices may be stuck."),
            $review > 0 => new C(self::SYNC, C::WARNING, "{$review} mobile conflict(s) wait for a manager's review."),
            default => new C(self::SYNC, C::OK, 'No mobile change is stuck or waiting for review.'),
        };
    }
}
