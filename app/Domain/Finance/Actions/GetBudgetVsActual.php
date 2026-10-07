<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Models\CostCentre;
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
            ->select([])->selectRaw('journal_lines.account_id, journal_lines.cost_centre_id, sum(journal_lines.debit_minor) as debit, sum(journal_lines.credit_minor) as credit')
            ->groupBy('journal_lines.account_id', 'journal_lines.cost_centre_id')->reorder()->get()
            ->keyBy(fn ($r) => $this->key($r->account_id, $r->cost_centre_id));

        $planned = $budget->lines->where('month', '<=', $month)->groupBy(fn ($l) => $this->key($l->account_id, $l->cost_centre_id));

        // Spending or income nobody budgeted still shows, against a plan of zero.
        $accounts = Account::whereIn('id', $actuals->pluck('account_id')->all())->get()->keyBy('id');
        $unplanned = $actuals->keys()->diff($planned->keys())->filter(fn ($key) => in_array($accounts[$actuals[$key]->account_id]->type, [AccountType::Revenue, AccountType::Expense], true));
        $centres = CostCentre::whereIn('id', $unplanned->map(fn ($key) => $actuals[$key]->cost_centre_id)->filter()->all())->get()->keyBy('id');

        $rows = $planned->map(fn ($lines, $key) => $this->row($lines->first()->account, $lines->first()->costCentre, (int) $lines->sum('amount_minor'), $actuals[$key] ?? null))
            ->concat($unplanned->map(fn ($key) => $this->row($accounts[$actuals[$key]->account_id], $centres[$actuals[$key]->cost_centre_id] ?? null, 0, $actuals[$key])))
            ->sortBy(fn ($r) => $r['account']->code.($r['cost_centre']->code ?? ''))->values()->all();

        $sum = fn (AccountType $type, string $field) => (int) collect($rows)->where('type', $type)->sum($field);

        return ['through_month' => $month, 'rows' => $rows, 'totals' => [
            'revenue_budget_minor' => $sum(AccountType::Revenue, 'budget_minor'), 'revenue_actual_minor' => $sum(AccountType::Revenue, 'actual_minor'),
            'expense_budget_minor' => $sum(AccountType::Expense, 'budget_minor'), 'expense_actual_minor' => $sum(AccountType::Expense, 'actual_minor'),
        ]];
    }

    private function key(int $accountId, ?int $costCentreId): string
    {
        return $accountId.'|'.($costCentreId ?? '');
    }

    /** @return array<string, mixed> */
    private function row(Account $account, ?CostCentre $centre, int $planned, ?object $sums): array
    {
        $revenue = $account->type === AccountType::Revenue;
        $actual = $revenue ? (int) ($sums->credit ?? 0) - (int) ($sums->debit ?? 0) : (int) ($sums->debit ?? 0) - (int) ($sums->credit ?? 0);
        $variance = $actual - $planned;

        return [
            'account' => $account, 'cost_centre' => $centre, 'type' => $account->type,
            'budget_minor' => $planned, 'actual_minor' => $actual, 'variance_minor' => $variance,
            'variance_percent' => $planned > 0 ? bcmul(bcdiv((string) $variance, (string) $planned, 4), '100', 1) : null,
            'favourable' => $revenue ? $variance >= 0 : $variance <= 0,
        ];
    }
}
