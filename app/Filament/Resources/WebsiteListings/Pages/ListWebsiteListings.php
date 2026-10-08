<?php

namespace App\Filament\Resources\WebsiteListings\Pages;

use App\Filament\Resources\WebsiteListings\WebsiteListingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteListings extends ListRecords
{
    protected static string $resource = WebsiteListingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
