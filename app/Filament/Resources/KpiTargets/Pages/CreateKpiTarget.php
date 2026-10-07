<?php

namespace App\Filament\Resources\KpiTargets\Pages;

use App\Domain\Reporting\Actions\SetKpiTarget;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\KpiTargets\KpiTargetResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateKpiTarget extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = KpiTargetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SetKpiTarget::class)($data['kpi_key'], (int) $data['year'], filled($data['month'] ?? null) ? (int) $data['month'] : null, (string) $data['target_value'], $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
