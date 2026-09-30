<?php

namespace App\Filament\Resources\Culling\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Culling\CullingResource;
use App\Filament\Support\HealthSubmissions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCulling extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = CullingResource::class;

    protected static ?string $title = 'Cull animal';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return HealthSubmissions::cull(Animal::findOrFail($data['animal_id']), $data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
