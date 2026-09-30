<?php

namespace App\Filament\Resources\VeterinaryVisits\Pages;

use App\Filament\Resources\VeterinaryVisits\VeterinaryVisitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVeterinaryVisits extends ListRecords
{
    protected static string $resource = VeterinaryVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record vet visit')];
    }
}
