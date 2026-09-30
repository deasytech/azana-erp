<?php

namespace App\Filament\Resources\Quarantine\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Quarantine\QuarantineResource;
use App\Filament\Support\HealthSubmissions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuarantine extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = QuarantineResource::class;

    protected static ?string $title = 'Start quarantine';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return HealthSubmissions::quarantine(Animal::findOrFail($data['animal_id']), $data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
