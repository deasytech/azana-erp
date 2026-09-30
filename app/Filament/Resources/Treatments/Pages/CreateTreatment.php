<?php

namespace App\Filament\Resources\Treatments\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Treatments\TreatmentResource;
use App\Filament\Support\HealthSubmissions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTreatment extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = TreatmentResource::class;

    protected static ?string $title = 'Record treatment';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return HealthSubmissions::treat(Animal::findOrFail($data['animal_id']), $data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
