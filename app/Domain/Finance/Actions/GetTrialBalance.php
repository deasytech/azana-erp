<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Services\Ledger;
use Carbon\CarbonInterface;

/**
 * Every account with its total debits and credits up to a date, and its balance (on the side the account normally grows).
 * Because every entry balances, the two totals are always equal; a difference would mean the books were tampered with.
 */
class GetTrialBalance
{
    public function __construct(private readonly Ledger $ledger) {}

    /** @return array{rows: list<array<string, mixed>>, debit_minor: int, credit_minor: int, balanced: bool} */
    public function __invoke(?CarbonInterface $asOf = null): array
    {
        $sums = $this->ledger->lines(null, $asOf)->selectRaw('journal_lines.account_id, sum(journal_lines.debit_minor) as debit, sum(journal_lines.credit_minor) as credit')
            ->groupBy('journal_lines.account_id')->reorder()->get()->keyBy('account_id');

        $rows = Account::orderBy('code')->get()->map(function (Account $a) use ($sums) {
            $debit = (int) ($sums[$a->id]->debit ?? 0);
            $credit = (int) ($sums[$a->id]->credit ?? 0);

            return ['account' => $a, 'debit_minor' => $debit, 'credit_minor' => $credit, 'balance_minor' => $a->type->isDebitNormal() ? $debit - $credit : $credit - $debit];
        })->filter(fn ($r) => $r['debit_minor'] || $r['credit_minor'])->values()->all();

        $debit = array_sum(array_column($rows, 'debit_minor'));
        $credit = array_sum(array_column($rows, 'credit_minor'));

        return ['rows' => $rows, 'debit_minor' => $debit, 'credit_minor' => $credit, 'balanced' => $debit === $credit];
    }
}
