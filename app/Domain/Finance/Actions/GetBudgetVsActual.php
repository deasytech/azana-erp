<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Services\Ledger;
use App\Enums\AccountType;
use Carbon\Carbon;

/**
 * Plan against posted actuals for the budget's year, from January to a chosen month (default the current one for the current year, else December).
 * Actual revenue is credits less debits; actual expense is debits less credits. Variance is actual less budget; it is favourable when
 * revenue is above plan or an expense is below it.
 */
class GetBudgetVsActual
{
    public function __construct(private readonly Ledger $ledger) {}

    /** @return array{through_month: int, rows: list<array<string, mixed>>, totals: array<string, int>} */
    public function __invoke(Budget $budget, ?int $throughMonth = null): array
    {
        $year = $budget->fiscal_year;
        $month = $throughMonth ?? ($year === (int) now()->year ? (int) now()->month : 12);
        $month = max(1, min(12, $month));
        $budget->load('lines.account', 'lines.costCentre');

        $actuals = $this->ledger->lines(Carbon::create($year, 1, 1), Carbon::create($year, $month, 1)->endOfMonth())
            ->selectRaw('journal_lines.account_id, journal_lines.cost_centre_id, sum(journal_lines.debit_minor) as debit, sum(journal_lines.credit_minor) as credit')
            ->groupBy('journal_lines.account_id', 'journal_lines.cost_centre_id')->reorder()->get()
            ->keyBy(fn ($r) => $r->account_id.'|'.($r->cost_centre_id ?? ''));

        $rows = $budget->lines->where('month', '<=', $month)->groupBy(fn ($l) => $l->account_id.'|'.($l->cost_centre_id ?? ''))->map(function ($lines, $key) use ($actuals) {
            $first = $lines->first();
            $revenue = $first->account->type === AccountType::Revenue;
            $planned = (int) $lines->sum('amount_minor');
            $a = $actuals[$key] ?? null;
            $actual = $revenue ? (int) ($a->credit ?? 0) - (int) ($a->debit ?? 0) : (int) ($a->debit ?? 0) - (int) ($a->credit ?? 0);
            $variance = $actual - $planned;

            return [
                'account' => $first->account, 'cost_centre' => $first->costCentre, 'type' => $first->account->type,
                'budget_minor' => $planned, 'actual_minor' => $actual, 'variance_minor' => $variance,
                'variance_percent' => $planned > 0 ? bcmul(bcdiv((string) $variance, (string) $planned, 4), '100', 1) : null,
                'favourable' => $revenue ? $variance >= 0 : $variance <= 0,
            ];
        })->sortBy(fn ($r) => $r['account']->code.($r['cost_centre']->code ?? ''))->values()->all();

        $sum = fn (AccountType $type, string $field) => (int) collect($rows)->where('type', $type)->sum($field);

        return ['through_month' => $month, 'rows' => $rows, 'totals' => [
            'revenue_budget_minor' => $sum(AccountType::Revenue, 'budget_minor'), 'revenue_actual_minor' => $sum(AccountType::Revenue, 'actual_minor'),
            'expense_budget_minor' => $sum(AccountType::Expense, 'budget_minor'), 'expense_actual_minor' => $sum(AccountType::Expense, 'actual_minor'),
        ]];
    }
}
