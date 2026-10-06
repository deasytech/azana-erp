<?php

namespace App\Filament\Resources\SemenBoars\Pages;

use App\Domain\Semen\Actions\ManageSemenBoar;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\SemenBoars\SemenBoarResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSemenBoar extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = SemenBoarResource::class;

    protected static ?string $title = 'Add a boar to the semen programme';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ManageSemenBoar::class)->enrol((int) $data['animal_id'], collect($data)->except('animal_id')->all());
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
