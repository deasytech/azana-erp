<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Domain\Sales\Actions\SaveCustomer;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomer extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveCustomer::class)($data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return CustomerResource::getUrl('view', ['record' => $this->record]);
    }
}
