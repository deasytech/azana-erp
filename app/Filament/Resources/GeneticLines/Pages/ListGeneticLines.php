<?php

namespace App\Filament\Resources\GeneticLines\Pages;

use App\Filament\Resources\GeneticLines\GeneticLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeneticLines extends ListRecords
{
    protected static string $resource = GeneticLineResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
