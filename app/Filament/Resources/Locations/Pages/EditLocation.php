<?php

namespace App\Filament\Resources\Locations\Pages;

use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Locations\LocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLocation extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
