<?php

namespace App\Filament\Resources\Quarantine\Pages;

use App\Filament\Resources\Quarantine\QuarantineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuarantines extends ListRecords
{
    protected static string $resource = QuarantineResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Start quarantine')];
    }
}
