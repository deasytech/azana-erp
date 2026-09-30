<?php

namespace App\Filament\Resources\BiosecurityChecklistItems\Pages;

use App\Filament\Resources\BiosecurityChecklistItems\BiosecurityChecklistItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBiosecurityChecklistItem extends EditRecord
{
    protected static string $resource = BiosecurityChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
