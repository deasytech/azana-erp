<?php

namespace App\Filament\Resources\FeedTypes\Pages;

use App\Filament\Resources\FeedTypes\FeedTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeedTypes extends ListRecords
{
    protected static string $resource = FeedTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
