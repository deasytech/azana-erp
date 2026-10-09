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
            ->mapWithKeys(fn ($r) => [$this->key($r) => [bcadd((string) $r->qty, '0', 3), (int) $r->value]]);

        $layers = InventoryLayer::query()
            ->selectRaw('inventory_item_id, inventory_location_id, inventory_batch_id, SUM(remaining_quantity) as qty, SUM(remaining_value_minor) as value')
            ->groupBy('inventory_item_id', 'inventory_location_id', 'inventory_batch_id')->get()
            ->mapWithKeys(fn ($r) => [$this->key($r) => [bcadd((string) $r->qty, '0', 3), (int) $r->value]]);

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
        $invoices = $this->posted('invoice');
        $payments = $this->posted('payment');
        $invoiced = (int) Invoice::where('is_historical', false)->whereIn('id', $invoices)->sum('total_minor');
        $received = (int) Payment::where('is_historical', false)->whereNull('voided_at')->whereIn('id', $payments)->sum('amount_minor');
        $unposted = Invoice::where('is_historical', false)->whereNotIn('id', $invoices)->count()
            + Payment::where('is_historical', false)->whereNull('voided_at')->whereNotIn('id', $payments)->count();

        return $this->compare(self::RECEIVABLES, $this->accountMovement('receivables', ['invoice:%', 'payment:%']), $invoiced - $received, $unposted, 'invoices and receipts');
    }

    private function payables(): C
    {
        $invoices = $this->posted('supplier-invoice');
        $payments = $this->posted('supplier-payment');
        $invoiced = (int) SupplierInvoice::whereNull('voided_at')->whereIn('id', $invoices)->sum('total_minor');
        $paid = (int) SupplierPayment::where('status', PaymentStatus::Paid)->whereIn('id', $payments)->sum('amount_minor');
        $unposted = SupplierInvoice::whereNull('voided_at')->whereNotIn('id', $invoices)->count()
            + SupplierPayment::where('status', PaymentStatus::Paid)->whereNotIn('id', $payments)->count();

        return $this->compare(self::PAYABLES, $this->accountMovement('payables', ['supplier-invoice:%', 'supplier-payment:%'], creditNormal: true), $invoiced - $paid, $unposted, 'supplier invoices and payments');
    }

    /** Ids of the documents of one kind that have a journal entry (their source key is "kind:id"). @return list<int> */
    private function posted(string $kind): array
    {
        return JournalEntry::where('source_key', 'like', "{$kind}:%")->pluck('source_key')->map(fn (string $k) => (int) substr($k, strlen($kind) + 1))->all();
    }

    private function compare(string $name, int $ledger, int $documents, int $unposted, string $what): C
    {
        $note = $unposted > 0 ? " {$unposted} document(s) are still waiting to be posted (the hourly posting run, or Finance > Journal)." : '';

        return $ledger === $documents
            ? new C($name, $unposted > 0 ? C::WARNING : C::OK, "The ledger agrees with the posted {$what}.{$note}")
            : new C($name, C::FAILED, 'The ledger shows '.number_format($ledger / 100, 2).' but the posted '.$what.' come to '.number_format($documents / 100, 2).'.'.$note);
    }

    /** Net movement on a system account from entries made out of operational documents (a reversal nets the original off). @param list<string> $prefixes */
    private function accountMovement(string $systemKey, array $prefixes, bool $creditNormal = false): int
    {
        $account = DB::table('accounts')->where('system_key', $systemKey)->value('id');

        if (! $account) {
            return 0;
        }

        $net = fn ($entries) => (int) DB::table('journal_lines')->where('account_id', $account)->whereIn('journal_entry_id', $entries)
            ->selectRaw('COALESCE(SUM(debit_minor - credit_minor), 0) as net')->value('net');

        $sources = DB::table('journal_entries')->where('status', JournalStatus::Posted->value)->where(function ($q) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                $q->orWhere('source_key', 'like', $prefix);
            }
        });
        // A reversal of a document entry has no source key of its own; it nets the entry it reverses.
        $ids = (clone $sources)->pluck('id');
        $reversals = DB::table('journal_entries')->where('status', JournalStatus::Posted->value)->whereIn('reverses_id', $ids)->pluck('id');
        $total = $net($ids->merge($reversals));

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
