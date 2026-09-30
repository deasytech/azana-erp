<?php

namespace App\Filament\Resources\HeatEvents\Pages;

use App\Filament\Resources\HeatEvents\HeatEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHeatEvents extends ListRecords
{
    protected static string $resource = HeatEventResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record heat')];
    }
}
