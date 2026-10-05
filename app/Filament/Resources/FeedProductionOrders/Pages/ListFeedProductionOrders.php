<?php

namespace App\Filament\Resources\FeedProductionOrders\Pages;

use App\Filament\Resources\FeedProductionOrders\FeedProductionOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeedProductionOrders extends ListRecords
{
    protected static string $resource = FeedProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New production order')];
    }
}
