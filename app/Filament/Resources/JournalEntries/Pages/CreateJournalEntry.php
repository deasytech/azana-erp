<?php

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Domain\Finance\Actions\PostManualJournal;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJournalEntry extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = JournalEntryResource::class;

    protected static ?string $title = 'New manual entry';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $lines = array_map(fn (array $l) => ['account_id' => (int) $l['account_id'], 'cost_centre_id' => $l['cost_centre_id'] ?: null, 'debit_minor' => (int) ($l['debit_minor'] ?? 0), 'credit_minor' => (int) ($l['credit_minor'] ?? 0)], $data['lines']);

            return app(PostManualJournal::class)(Carbon::parse($data['entry_date']), $data['description'], $lines);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return JournalEntryResource::getUrl('view', ['record' => $this->record]);
    }
}
