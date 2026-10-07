<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Budget;
use App\Domain\System\Actions\AssertMayDecide;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AccountType;
use App\Enums\BudgetStatus;
use App\Enums\Module;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A budget is a year's plan: lines of account, optional cost centre, month (1-12) and amount. Only revenue and expense accounts
 * are budgeted. A draft can be rewritten; once approved it is locked (make a new budget to change the plan).
 */
class SaveBudget
{
    public function __construct(private readonly AssertMayDecide $assertMayDecide) {}

    /** @param list<array<string, mixed>> $lines */
    public function __invoke(string $name, int $fiscalYear, array $lines, ?string $notes = null, ?Budget $budget = null, ?User $actor = null): Budget
    {
        if (trim($name) === '' || $fiscalYear < 2000 || $fiscalYear > 2100) {
            throw new DomainException('Give the budget a name and a year.', 'budget_invalid');
        }

        $rows = array_map(function (array $line) {
            $account = Account::find($line['account_id'] ?? 0);
            $month = $line['month'] ?? 0;

            if (! $account || ! in_array($account->type, [AccountType::Revenue, AccountType::Expense], true)) {
                throw new DomainException('A budget line needs a revenue or expense account.', 'budget_account');
            }

            if (! is_int($month) || $month < 1 || $month > 12 || ! is_int($line['amount_minor'] ?? null) || $line['amount_minor'] < 0) {
                throw new DomainException('Each budget line needs a month from 1 to 12 and an amount of zero or more.', 'budget_line');
            }

            return ['account_id' => $account->id, 'cost_centre_id' => $line['cost_centre_id'] ?? null, 'month' => $month, 'amount_minor' => $line['amount_minor']];
        }, array_values($lines));

        return DB::transaction(function () use ($name, $fiscalYear, $rows, $notes, $budget, $actor) {
            if ($budget) {
                $budget = Budget::lockForUpdate()->findOrFail($budget->id);
                $budget->isApproved() && throw new DomainException('An approved budget cannot be changed.', 'budget_locked');
                $budget->update(['name' => trim($name), 'fiscal_year' => $fiscalYear, 'notes' => $notes]);
                $budget->lines()->delete();
            } else {
                $budget = Budget::create(['name' => trim($name), 'fiscal_year' => $fiscalYear, 'notes' => $notes, 'created_by' => ($actor ?? Auth::user())?->getKey()]);
            }

            $budget->lines()->createMany($rows);

            return $budget->load('lines');
        });
    }

    public function approve(Budget $budget, User $approver): Budget
    {
        return DB::transaction(function () use ($budget, $approver) {
            $budget = Budget::lockForUpdate()->findOrFail($budget->id);
            ($this->assertMayDecide)($approver, $budget->created_by, Module::Finance, 'budget');

            $budget->isApproved() && throw new DomainException('This budget is already approved.', 'budget_state');
            $budget->lines()->exists() || throw new DomainException('Add budget lines before approving.', 'budget_empty');
            $budget->update(['status' => BudgetStatus::Approved, 'approved_by' => $approver->id, 'approved_at' => now()]);

            return $budget;
        });
    }
}
