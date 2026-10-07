<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\CashTransaction;
use App\Domain\Finance\Models\ExpenseRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Voids an expense or a cash transaction: the record is kept, marked void, and its journal entry is reversed. */
class VoidFinanceRecord
{
    public function __construct(private readonly ReverseJournal $reverse) {}

    public function __invoke(ExpenseRecord|CashTransaction $record, string $reason, ?User $actor = null): ExpenseRecord|CashTransaction
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void this record.', 'reason_required');
        }

        return DB::transaction(function () use ($record, $reason, $actor) {
            $record = $record::lockForUpdate()->findOrFail($record->id);

            if ($record->isVoided()) {
                throw new DomainException("{$record->number} is already voided.", 'already_voided');
            }

            ($this->reverse)($record->journalEntry, "{$record->number} voided - {$reason}", null, $actor);
            $record->forceFill(['voided_at' => now(), 'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason)])->save();

            return $record;
        });
    }
}
