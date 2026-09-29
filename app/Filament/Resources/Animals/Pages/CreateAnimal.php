<?php

namespace App\Filament\Resources\Animals\Pages;

use App\Domain\Animal\Actions\RegisterAnimal;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Animals\AnimalResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAnimal extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = AnimalResource::class;

    protected static ?string $title = 'Register animal';

    /** Registration always goes through the domain action so web, API and offline sync share one rule set. */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RegisterAnimal::class)($data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
