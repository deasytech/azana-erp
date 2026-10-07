<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\Ledger;
use App\Enums\AccountType;
use Carbon\CarbonInterface;

/**
 * Cash flow from the posted entries that touch a cash or bank account. Money in or out is grouped by what the entry
 * was for (taken from the other side of the entry): customer receipts, supplier payments, sales, expenses, other.
 */
class GetCashFlow
{
    public function __construct(private readonly Ledger $ledger) {}

    /** @return array{opening_minor: int, closing_minor: int, in_minor: int, out_minor: int, net_minor: int, inflows: array<string, int>, outflows: array<string, int>} */
    public function __invoke(CarbonInterface $from, CarbonInterface $to): array
    {
        $cashIds = Account::whereIn('system_key', ['cash', 'bank'])->pluck('id')->all();
        $net = fn ($lines) => (int) $lines->sum('debit_minor') - (int) $lines->sum('credit_minor');
        $opening = $net($this->ledger->lines(null, $from->copy()->subDay())->whereIn('journal_lines.account_id', $cashIds)->get());

        $entries = JournalEntry::with('lines.account')->whereIn('id', $this->ledger->lines($from, $to)->whereIn('journal_lines.account_id', $cashIds)->select('journal_lines.journal_entry_id'))->get();
        [$inflows, $outflows] = [[], []];

        foreach ($entries as $entry) {
            $cash = $entry->lines->whereIn('account_id', $cashIds);
            $amount = $net($cash);
            $label = $this->purpose($entry->lines->whereNotIn('account_id', $cashIds)->first()?->account);

            if ($amount >= 0) {
                $inflows[$label] = ($inflows[$label] ?? 0) + $amount;
            } else {
                $outflows[$label] = ($outflows[$label] ?? 0) - $amount;
            }
        }

        ksort($inflows);
        ksort($outflows);
        $in = array_sum($inflows);
        $out = array_sum($outflows);

        return ['opening_minor' => $opening, 'closing_minor' => $opening + $in - $out, 'in_minor' => $in, 'out_minor' => $out, 'net_minor' => $in - $out, 'inflows' => $inflows, 'outflows' => $outflows];
    }

    private function purpose(?Account $other): string
    {
        return match (true) {
            $other === null => 'Other',
            $other->system_key === 'receivables' => 'Customer receipts',
            $other->system_key === 'payables' => 'Supplier payments',
            $other->type === AccountType::Revenue => 'Sales and other income',
            $other->type === AccountType::Expense => 'Operating expenses',
            default => 'Other',
        };
    }
}
