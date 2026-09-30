<?php

namespace App\Filament\Resources\LabResults\Pages;

use App\Filament\Resources\LabResults\LabResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLabResults extends ListRecords
{
    protected static string $resource = LabResultResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record lab result')];
    }
}
