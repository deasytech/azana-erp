<?php

namespace App\Filament\Resources\SemenBatches\Pages;

use App\Filament\Resources\SemenBatches\SemenBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSemenBatches extends ListRecords
{
    protected static string $resource = SemenBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record collection')];
    }
}
