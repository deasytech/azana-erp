<?php

namespace App\Filament\Resources\ProductionUnits\Pages;

use App\Filament\Resources\ProductionUnits\ProductionUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionUnits extends ListRecords
{
    protected static string $resource = ProductionUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
