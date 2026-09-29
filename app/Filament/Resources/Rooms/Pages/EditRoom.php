<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Rooms\RoomResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
