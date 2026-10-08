<?php

namespace App\Filament\Resources\WebsiteListings\Pages;

use App\Domain\System\Exceptions\DomainException;
use App\Domain\Website\Actions\SaveListing;
use App\Filament\Resources\WebsiteListings\WebsiteListingResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWebsiteListing extends CreateRecord
{
    protected static string $resource = WebsiteListingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveListing::class)($data);
        } catch (DomainException $e) {
            Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();
            $this->halt();
        }
    }
}
