<?php

namespace App\Filament\Resources\LabResults\Pages;

use App\Domain\Health\Actions\RecordLabResult;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\LabResults\LabResultResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLabResult extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = LabResultResource::class;

    protected static ?string $title = 'Record lab result';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordLabResult::class)($data['sample_type'], $data['test_name'], Carbon::parse($data['sampled_on']), [
                'animal_id' => $data['animal_id'] ?? null,
                'resulted_on' => filled($data['resulted_on'] ?? null) ? Carbon::parse($data['resulted_on']) : null,
                'result' => $data['result'] ?? null,
                'is_abnormal' => (bool) ($data['is_abnormal'] ?? false),
                'lab_name' => $data['lab_name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
