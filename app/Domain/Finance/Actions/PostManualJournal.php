<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Finance\Models\JournalEntry;
use App\Enums\JournalStatus;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Enters a journal typed in by a person. Above the approval limit (setting finance.journal_approval_threshold_minor; 0 means every
 * manual journal) it waits as pending for somebody else to approve; at or below the limit it posts at once.
 */
class PostManualJournal
{
    public function __construct(private readonly PostJournal $post, private readonly ResolveSettings $settings) {}

    /** @param list<array<string, mixed>> $lines */
    public function __invoke(CarbonInterface $date, string $description, array $lines, ?User $actor = null): JournalEntry
    {
        $total = array_sum(array_map(fn ($l) => (int) ($l['debit_minor'] ?? 0), $lines));
        $limit = (int) $this->settings->get('finance.journal_approval_threshold_minor');

        return ($this->post)($date, $description, $lines, $total > $limit ? JournalStatus::Pending : JournalStatus::Posted, null, $actor);
    }
}
