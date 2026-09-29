<?php

namespace App\Filament\Resources\ProductionUnits\Pages;

use App\Filament\Resources\ProductionUnits\ProductionUnitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionUnit extends EditRecord
{
    protected static string $resource = ProductionUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
