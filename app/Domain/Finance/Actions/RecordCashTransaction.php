<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CashTransaction;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CashDirection;
use App\Enums\JournalStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Money in or out of a cash or bank account, against another account (income, a cost, a loan...). Cash flow is read from these and from posted payments. */
class RecordCashTransaction
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly PostJournal $post) {}

    public function __invoke(CarbonInterface $date, CashDirection $direction, Account|int $cashAccount, Account|int $counterAccount, int $amountMinor, ?int $costCentreId = null, ?string $reference = null, ?string $description = null, ?User $actor = null): CashTransaction
    {
        if ($amountMinor < 1) {
            throw new DomainException('A cash transaction must be more than zero.', 'cash_amount');
        }

        $cash = Account::findOrFail($cashAccount instanceof Account ? $cashAccount->id : $cashAccount);
        $counter = Account::findOrFail($counterAccount instanceof Account ? $counterAccount->id : $counterAccount);

        if (! in_array($cash->system_key, ['cash', 'bank'], true)) {
            throw new DomainException('Choose the cash or bank account the money moved through.', 'cash_account');
        }

        if ($counter->id === $cash->id || in_array($counter->system_key, ['cash', 'bank'], true)) {
            throw new DomainException('The other side of a cash transaction cannot be a cash or bank account.', 'cash_counter');
        }

        return DB::transaction(function () use ($date, $direction, $cash, $counter, $amountMinor, $costCentreId, $reference, $description, $actor) {
            $number = sprintf('CT-%06d', ($this->nextNumber)('cash_transaction'));
            $in = $direction === CashDirection::In;
            $entry = ($this->post)($date, "Cash {$direction->value} {$number}: {$counter->name}", [
                ['account_id' => $cash->id, ($in ? 'debit_minor' : 'credit_minor') => $amountMinor],
                ['account_id' => $counter->id, 'cost_centre_id' => $costCentreId, ($in ? 'credit_minor' : 'debit_minor') => $amountMinor],
            ], JournalStatus::Posted, "cash:{$number}", $actor);

            return CashTransaction::create([
                'number' => $number, 'transaction_date' => $date, 'direction' => $direction, 'cash_account_id' => $cash->id,
                'counter_account_id' => $counter->id, 'cost_centre_id' => $costCentreId, 'amount_minor' => $amountMinor,
                'reference' => $reference, 'description' => $description, 'journal_entry_id' => $entry->id,
                'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);
        });
    }
}
