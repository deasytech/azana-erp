<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\JournalLine;
use App\Enums\JournalStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** Read access to the posted ledger: only lines of entries that have taken effect (not pending or rejected). */
class Ledger
{
    /** @return Builder<JournalLine> */
    public function lines(?CarbonInterface $from = null, ?CarbonInterface $to = null): Builder
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->when($from, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $to->toDateString()))
            ->select('journal_lines.*');
    }
}
