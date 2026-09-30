<?php

namespace App\Filament\Resources\Mortality\Pages;

use App\Filament\Resources\Mortality\MortalityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMortalities extends ListRecords
{
    protected static string $resource = MortalityResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record death')];
    }
}
