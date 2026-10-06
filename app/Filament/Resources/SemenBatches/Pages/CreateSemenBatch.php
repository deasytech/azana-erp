<?php

namespace App\Filament\Resources\SemenBatches\Pages;

use App\Domain\Semen\Actions\RecordSemenCollection;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\SemenBatches\SemenBatchResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSemenBatch extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = SemenBatchResource::class;

    protected static ?string $title = 'Record a collection';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordSemenCollection::class)((int) $data['animal_id'], Carbon::parse($data['collected_at']), (string) $data['volume_ml'],
                collect($data)->only(['ph', 'colour', 'odour', 'technician_name', 'notes'])->filter(fn ($v) => filled($v))->all());
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return SemenBatchResource::getUrl('view', ['record' => $this->record]);
    }
}
