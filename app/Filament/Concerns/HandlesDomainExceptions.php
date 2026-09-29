<?php

namespace App\Filament\Concerns;

use App\Domain\System\Exceptions\DomainException;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/** Shows business-rule violations as a form notification instead of an error page. */
trait HandlesDomainExceptions
{
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
        Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();

        $this->halt();
    }
}
