<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Domain\Sales\Actions\CreateSalesOrder as DraftOrder;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSalesOrder extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = SalesOrderResource::class;

    protected static ?string $title = 'New order';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(DraftOrder::class)((int) $data['customer_id'], array_map(fn (array $line) => array_filter($line, fn ($v) => $v !== null && $v !== ''), $data['lines']),
                Carbon::parse($data['ordered_on']), $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return SalesOrderResource::getUrl('view', ['record' => $this->record]);
    }
}
