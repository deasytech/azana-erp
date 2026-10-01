<?php

namespace App\Filament\Resources\FeedTypes\Pages;

use App\Filament\Resources\FeedTypes\FeedTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFeedType extends EditRecord
{
    protected static string $resource = FeedTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
