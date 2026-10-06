<?php

namespace App\Filament\Resources\SemenBoars\Pages;

use App\Domain\Semen\Actions\ManageSemenBoar;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\SemenBoars\SemenBoarResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSemenBoar extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = SemenBoarResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ManageSemenBoar::class)->update($record, $data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
