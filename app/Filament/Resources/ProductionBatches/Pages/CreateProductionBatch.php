<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Domain\Production\Actions\OpenProductionBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductionBatch extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = ProductionBatchResource::class;

    protected static ?string $title = 'Start batch';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(OpenProductionBatch::class)([...$data, 'started_on' => Carbon::parse($data['started_on'])]);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProductionBatchResource::getUrl('view', ['record' => $this->record]);
    }
}
