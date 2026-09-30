<?php

namespace App\Filament\Resources\Vaccinations\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Vaccinations\VaccinationResource;
use App\Filament\Support\HealthSubmissions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVaccination extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = VaccinationResource::class;

    protected static ?string $title = 'Record vaccination';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return HealthSubmissions::vaccinate(Animal::findOrFail($data['animal_id']), $data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
