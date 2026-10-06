<?php

namespace App\Filament\Resources\MeatProductionBatches\Pages;

use App\Filament\Resources\MeatProductionBatches\MeatProductionBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeatProductionBatches extends ListRecords
{
    protected static string $resource = MeatProductionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Make meat')];
    }
}
