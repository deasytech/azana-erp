<?php

namespace App\Filament\Resources\FeedConsumption\Pages;

use App\Filament\Resources\FeedConsumption\FeedConsumptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeedConsumption extends ListRecords
{
    protected static string $resource = FeedConsumptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record feed')];
    }
}
