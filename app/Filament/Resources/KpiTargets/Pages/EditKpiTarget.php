<?php

namespace App\Filament\Resources\KpiTargets\Pages;

use App\Domain\Reporting\Actions\SetKpiTarget;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\KpiTargets\KpiTargetResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditKpiTarget extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = KpiTargetResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['target_value'] = rtrim(rtrim((string) $data['target_value'], '0'), '.');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SetKpiTarget::class)($data['kpi_key'], (int) $data['year'], filled($data['month'] ?? null) ? (int) $data['month'] : null, (string) $data['target_value'], $data['notes'] ?? null, $record);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
