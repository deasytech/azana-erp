<?php

namespace App\Filament\Resources\BiosecurityChecklistItems\Pages;

use App\Filament\Resources\BiosecurityChecklistItems\BiosecurityChecklistItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBiosecurityChecklistItems extends ListRecords
{
    protected static string $resource = BiosecurityChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
