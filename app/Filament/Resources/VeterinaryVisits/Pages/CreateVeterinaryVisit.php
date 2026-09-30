<?php

namespace App\Filament\Resources\VeterinaryVisits\Pages;

use App\Domain\Health\Actions\RecordVeterinaryVisit;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\VeterinaryVisits\VeterinaryVisitResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVeterinaryVisit extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = VeterinaryVisitResource::class;

    protected static ?string $title = 'Record vet visit';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordVeterinaryVisit::class)(Carbon::parse($data['visited_on']), $data['reason'], [
                'veterinarian_id' => $data['veterinarian_id'] ?? null,
                'veterinarian_name' => $data['veterinarian_name'] ?? null,
                'findings' => $data['findings'] ?? null,
                'recommendations' => $data['recommendations'] ?? null,
                'follow_up_on' => filled($data['follow_up_on'] ?? null) ? Carbon::parse($data['follow_up_on']) : null,
                'cost_minor' => $data['cost_minor'] ?? null,
            ]);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
