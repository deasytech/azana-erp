<?php

namespace App\Filament\Resources\BreedingServices\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Actions\RecordService;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ServiceMethod;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\BreedingServices\BreedingServiceResource;
use App\Models\User;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBreedingService extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = BreedingServiceResource::class;

    protected static ?string $title = 'Record service';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordService::class)(
                Animal::findOrFail($data['sow_id']),
                ServiceMethod::from($data['method']),
                Carbon::parse($data['serviced_on']),
                $data['boar_id'] ?? null,
                $data['semen_source'] ?? null,
                filled($data['technician_id'] ?? null) ? User::find($data['technician_id']) : null,
                $data['technician_name'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
