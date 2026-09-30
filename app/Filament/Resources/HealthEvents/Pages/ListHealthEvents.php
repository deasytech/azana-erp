<?php

namespace App\Filament\Resources\HealthEvents\Pages;

use App\Filament\Resources\HealthEvents\HealthEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHealthEvents extends ListRecords
{
    protected static string $resource = HealthEventResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Report health case')];
    }
}
