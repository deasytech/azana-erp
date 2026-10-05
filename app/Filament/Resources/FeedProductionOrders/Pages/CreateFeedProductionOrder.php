<?php

namespace App\Filament\Resources\FeedProductionOrders\Pages;

use App\Domain\Feed\Actions\CreateFeedProductionOrder as PlanOrder;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\FeedProductionOrders\FeedProductionOrderResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFeedProductionOrder extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = FeedProductionOrderResource::class;

    protected static ?string $title = 'New production order';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PlanOrder::class)((int) $data['feed_formula_id'], (string) $data['planned_output_kg'], Carbon::parse($data['planned_on']),
                (int) $data['source_location_id'], (int) $data['output_location_id'], $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return FeedProductionOrderResource::getUrl('view', ['record' => $this->record]);
    }
}
