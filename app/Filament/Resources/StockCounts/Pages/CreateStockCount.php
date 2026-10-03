<?php

namespace App\Filament\Resources\StockCounts\Pages;

use App\Domain\Inventory\Actions\StartStockCount;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\StockCounts\StockCountResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStockCount extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = StockCountResource::class;

    protected static ?string $title = 'Start stock count';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(StartStockCount::class)((int) $data['inventory_location_id'], Carbon::parse($data['counted_on']), $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return StockCountResource::getUrl('view', ['record' => $this->record]);
    }
}
