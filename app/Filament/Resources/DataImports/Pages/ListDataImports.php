<?php

namespace App\Filament\Resources\DataImports\Pages;

use App\Filament\Resources\DataImports\DataImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDataImports extends ListRecords
{
    protected static string $resource = DataImportResource::class;

    protected function getHeaderActions(): array
    {
        return [DataImportResource::templateActions(), CreateAction::make()->label('Upload a file')];
    }
}
