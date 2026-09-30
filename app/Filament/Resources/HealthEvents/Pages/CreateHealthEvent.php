<?php

namespace App\Filament\Resources\HealthEvents\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\HealthEvents\HealthEventResource;
use App\Filament\Support\HealthSubmissions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateHealthEvent extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = HealthEventResource::class;

    protected static ?string $title = 'Report health case';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return HealthSubmissions::reportCase(Animal::findOrFail($data['animal_id']), $data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
