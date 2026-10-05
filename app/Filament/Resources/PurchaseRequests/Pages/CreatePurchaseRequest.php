<?php

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Domain\Procurement\Actions\CreatePurchaseRequest as CreateRequest;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseRequest extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = PurchaseRequestResource::class;

    protected static ?string $title = 'New purchase request';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateRequest::class)($data['lines'], filled($data['needed_by'] ?? null) ? Carbon::parse($data['needed_by']) : null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseRequestResource::getUrl('view', ['record' => $this->record]);
    }
}
