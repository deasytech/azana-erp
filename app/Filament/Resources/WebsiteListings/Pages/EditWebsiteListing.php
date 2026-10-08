<?php

namespace App\Filament\Resources\WebsiteListings\Pages;

use App\Domain\System\Exceptions\DomainException;
use App\Domain\Website\Actions\SaveListing;
use App\Filament\Resources\WebsiteListings\WebsiteListingResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWebsiteListing extends EditRecord
{
    protected static string $resource = WebsiteListingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveListing::class)($data, $record);
        } catch (DomainException $e) {
            Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();
            $this->halt();
        }
    }
}
