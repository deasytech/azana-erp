<?php

namespace App\Filament\Concerns;

use App\Domain\System\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Model;

/** Shows business-rule violations on create/edit pages as a notification. */
trait HandlesDomainExceptions
{
    use NotifiesDomainErrors;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function failWith(DomainException $e): never
    {
        $this->notifyFailure($e);

        $this->halt();
    }
}
