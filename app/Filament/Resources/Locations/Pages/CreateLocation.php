<?php

namespace App\Filament\Resources\Locations\Pages;

use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Locations\LocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = LocationResource::class;
}
