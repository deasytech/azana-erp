<?php

namespace App\Filament\Resources\SlaughterBatches\Pages;

use App\Filament\Resources\SlaughterBatches\SlaughterBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlaughterBatches extends ListRecords
{
    protected static string $resource = SlaughterBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Schedule slaughter')];
    }
}
