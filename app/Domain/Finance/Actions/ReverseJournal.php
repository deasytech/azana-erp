<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\JournalEntry;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\JournalStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Undoes a posted entry with a new one that swaps every debit and credit. An entry can be reversed once, and a reversal cannot be reversed. */
class ReverseJournal
{
    public function __construct(private readonly PostJournal $post) {}

    public function __invoke(JournalEntry $entry, string $reason, ?CarbonInterface $date = null, ?User $actor = null): JournalEntry
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to reverse an entry.', 'reason_required');
        }

        return DB::transaction(function () use ($entry, $reason, $date, $actor) {
            $entry = JournalEntry::with('lines')->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status !== JournalStatus::Posted) {
                throw new DomainException('Only a posted entry can be reversed.', 'journal_state');
            }

            if ($entry->reverses_id !== null) {
                throw new DomainException('A reversing entry cannot itself be reversed; post a new entry instead.', 'journal_is_reversal');
            }

            if ($entry->reversal()->exists()) {
                throw new DomainException("{$entry->number} has already been reversed.", 'journal_reversed');
            }

            return ($this->post)(
                $date ?? now(), "Reversal of {$entry->number}: ".trim($reason),
                $entry->lines->map(fn ($l) => ['account_id' => $l->account_id, 'cost_centre_id' => $l->cost_centre_id, 'debit_minor' => $l->credit_minor, 'credit_minor' => $l->debit_minor, 'description' => $l->description])->all(),
                JournalStatus::Posted, null, $actor, $entry->id,
            );
        });
    }
}
