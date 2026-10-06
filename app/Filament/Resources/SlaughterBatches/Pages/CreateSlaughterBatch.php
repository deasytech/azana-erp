<?php

namespace App\Filament\Resources\SlaughterBatches\Pages;

use App\Domain\Slaughter\Actions\ManageSlaughterBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\SlaughterBatches\SlaughterBatchResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSlaughterBatch extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = SlaughterBatchResource::class;

    protected static ?string $title = 'Schedule a slaughter day';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ManageSlaughterBatch::class)->schedule(Carbon::parse($data['scheduled_on']), $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return SlaughterBatchResource::getUrl('view', ['record' => $this->record]);
    }
}
