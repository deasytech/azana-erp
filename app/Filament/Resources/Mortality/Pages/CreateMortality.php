<?php

namespace App\Filament\Resources\Mortality\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Mortality\MortalityResource;
use App\Filament\Support\HealthSubmissions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMortality extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = MortalityResource::class;

    protected static ?string $title = 'Record death';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return HealthSubmissions::recordDeath(Animal::findOrFail($data['animal_id']), $data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
