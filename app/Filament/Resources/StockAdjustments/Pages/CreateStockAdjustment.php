<?php

namespace App\Filament\Resources\StockAdjustments\Pages;

use App\Domain\Inventory\Actions\RequestStockAdjustment;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStockAdjustment extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = StockAdjustmentResource::class;

    protected static ?string $title = 'Request stock adjustment';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RequestStockAdjustment::class)((int) $data['inventory_item_id'], (int) $data['inventory_location_id'], $data['inventory_batch_id'] ?? null, (string) $data['quantity'], $data['reason']);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
