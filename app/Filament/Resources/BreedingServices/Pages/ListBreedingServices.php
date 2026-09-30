<?php

namespace App\Filament\Resources\BreedingServices\Pages;

use App\Filament\Resources\BreedingServices\BreedingServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBreedingServices extends ListRecords
{
    protected static string $resource = BreedingServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record service')];
    }
}
