<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Finance\Models\ExpenseRecord;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a cost against an expense account and a cost centre. Paid from a cash or bank account it reduces that account;
 * with no paying account it is owed (accounts payable). Posts its journal entry in the same step.
 */
class RecordExpense
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly PostJournal $post) {}

    public function __invoke(CarbonInterface $date, Account|int $account, CostCentre|int $costCentre, int $amountMinor, Account|int|null $paidFrom = null, ?string $payee = null, ?string $reference = null, ?string $description = null, ?User $actor = null): ExpenseRecord
    {
        if ($amountMinor < 1) {
            throw new DomainException('An expense must be more than zero.', 'expense_amount');
        }

        $account = Account::findOrFail($account instanceof Account ? $account->id : $account);
        $account->type === AccountType::Expense || throw new DomainException("{$account->label()} is not an expense account.", 'expense_account');
        $centre = CostCentre::findOrFail($costCentre instanceof CostCentre ? $costCentre->id : $costCentre);
        $creditor = $paidFrom ? Account::findOrFail($paidFrom instanceof Account ? $paidFrom->id : $paidFrom) : Account::system('payables');

        if ($paidFrom && ! in_array($creditor->system_key, ['cash', 'bank'], true)) {
            throw new DomainException('An expense is paid from the cash or bank account.', 'expense_paid_from');
        }

        return DB::transaction(function () use ($date, $account, $centre, $amountMinor, $creditor, $paidFrom, $payee, $reference, $description, $actor) {
            $number = sprintf('EX-%06d', ($this->nextNumber)('expense_record'));
            $entry = ($this->post)($date, "Expense {$number}: {$account->name}".($payee ? " ({$payee})" : ''), [
                ['account_id' => $account->id, 'cost_centre_id' => $centre->id, 'debit_minor' => $amountMinor],
                ['account_id' => $creditor->id, 'credit_minor' => $amountMinor],
            ], JournalStatus::Posted, "expense:{$number}", $actor);

            return ExpenseRecord::create([
                'number' => $number, 'expense_date' => $date, 'account_id' => $account->id, 'cost_centre_id' => $centre->id,
                'amount_minor' => $amountMinor, 'paid_from_account_id' => $paidFrom ? $creditor->id : null,
                'payee' => $payee, 'reference' => $reference, 'description' => $description,
                'journal_entry_id' => $entry->id, 'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);
        });
    }
}
