<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\JournalStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Writes a balanced journal entry. Each line is ['account_id', 'debit_minor' or 'credit_minor', optional 'cost_centre_id',
 * 'description']. Debits must equal credits, a line is one-sided, and nothing may be dated on or before the date the books
 * were closed through. A manual entry (status pending) takes effect only once somebody else approves it; entries made
 * by the system or by finance records are posted straight away. source_key makes a posting idempotent.
 */
class PostJournal
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly ResolveSettings $settings) {}

    /** @param list<array<string, mixed>> $lines */
    public function __invoke(CarbonInterface $date, string $description, array $lines, JournalStatus $status = JournalStatus::Posted, ?string $sourceKey = null, ?User $actor = null, ?int $reversesId = null): JournalEntry
    {
        if (trim($description) === '') {
            throw new DomainException('Describe the entry.', 'journal_description');
        }

        if ($date->gt(now()->addMinutes(5))) {
            throw new DomainException('An entry cannot be dated in the future.', 'journal_future');
        }

        $this->assertOpen($date);
        $rows = $this->rows($lines);
        $total = array_sum(array_column($rows, 'debit_minor'));

        if ($total !== array_sum(array_column($rows, 'credit_minor'))) {
            throw new DomainException('The entry does not balance: total debits must equal total credits.', 'journal_unbalanced');
        }

        try {
            return $this->write($date, $description, $rows, $total, $status, $sourceKey, $actor, $reversesId);
        } catch (UniqueConstraintViolationException $e) {
            // Another process posted the same document between our check and our insert: its entry is the one.
            return ($sourceKey ? JournalEntry::firstWhere('source_key', $sourceKey) : null) ?? throw $e;
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function write(CarbonInterface $date, string $description, array $rows, int $total, JournalStatus $status, ?string $sourceKey, ?User $actor, ?int $reversesId): JournalEntry
    {
        return DB::transaction(function () use ($date, $description, $rows, $total, $status, $sourceKey, $actor, $reversesId) {
            if ($sourceKey && ($existing = JournalEntry::firstWhere('source_key', $sourceKey))) {
                return $existing;
            }

            $entry = JournalEntry::create([
                'number' => sprintf('JE-%06d', ($this->nextNumber)('journal_entry')),
                'entry_date' => $date, 'description' => trim($description), 'status' => $status,
                'source_key' => $sourceKey, 'reverses_id' => $reversesId, 'total_minor' => $total,
                'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            $entry->lines()->createMany($rows);

            return $entry->load('lines');
        });
    }

    public function assertOpen(CarbonInterface $date): void
    {
        $closed = (string) $this->settings->get('finance.books_closed_through');

        if ($closed !== '' && $date->toDateString() <= $closed) {
            throw new DomainException("The books are closed through {$closed}; nothing can be posted on or before that date.", 'books_closed');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function rows(array $lines): array
    {
        if (count($lines) < 2) {
            throw new DomainException('An entry needs at least two lines.', 'journal_lines');
        }

        return array_map(function (array $line) {
            [$debit, $credit] = [$line['debit_minor'] ?? 0, $line['credit_minor'] ?? 0];

            if (! is_int($debit) || ! is_int($credit) || $debit < 0 || $credit < 0 || ($debit > 0) === ($credit > 0)) {
                throw new DomainException('Each line is either a debit or a credit, in whole minor units above zero.', 'journal_line_amount');
            }

            Account::where('is_active', true)->find($line['account_id'] ?? 0) ?? throw new DomainException('Choose an active account for every line.', 'journal_account');

            if (filled($line['cost_centre_id'] ?? null)) {
                CostCentre::where('is_active', true)->find($line['cost_centre_id']) ?? throw new DomainException('That cost centre is not active.', 'journal_cost_centre');
            }

            return [
                'account_id' => $line['account_id'], 'cost_centre_id' => $line['cost_centre_id'] ?? null,
                'debit_minor' => $debit, 'credit_minor' => $credit, 'description' => $line['description'] ?? null,
            ];
        }, array_values($lines));
    }
}
