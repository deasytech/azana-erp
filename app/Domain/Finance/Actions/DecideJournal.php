<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\JournalEntry;
use App\Domain\System\Actions\AssertMayDecide;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\JournalStatus;
use App\Enums\Module;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Approves (posts) or rejects a manual journal entry. By default the approver must not be the person who entered it. */
class DecideJournal
{
    public function __construct(private readonly AssertMayDecide $assertMayDecide, private readonly PostJournal $post) {}

    public function approve(JournalEntry $entry, User $approver, ?string $notes = null): JournalEntry
    {
        return $this->decide($entry, $approver, JournalStatus::Posted, $notes);
    }

    public function reject(JournalEntry $entry, User $approver, string $reason): JournalEntry
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to reject an entry.', 'reason_required');
        }

        return $this->decide($entry, $approver, JournalStatus::Rejected, trim($reason));
    }

    private function decide(JournalEntry $entry, User $approver, JournalStatus $outcome, ?string $notes): JournalEntry
    {
        return DB::transaction(function () use ($entry, $approver, $outcome, $notes) {
            $entry = JournalEntry::lockForUpdate()->findOrFail($entry->id);
            ($this->assertMayDecide)($approver, $entry->created_by, Module::Finance, 'journal entry');

            if ($entry->status !== JournalStatus::Pending) {
                throw new DomainException('This entry has already been decided.', 'journal_state');
            }

            // The date it takes effect must still be in an open period.
            $outcome === JournalStatus::Posted && $this->post->assertOpen($entry->entry_date);

            $entry->update(['status' => $outcome, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);

            return $entry;
        });
    }
}
