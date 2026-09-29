<?php

namespace App\Filament\Resources\GeneticLines\Pages;

use App\Filament\Resources\GeneticLines\GeneticLineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneticLine extends EditRecord
{
    protected static string $resource = GeneticLineResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
