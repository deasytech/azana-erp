<?php

namespace App\Filament\Resources\DataImports\Pages;

use App\Filament\Resources\DataImports\DataImportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDataImport extends ViewRecord
{
    protected static string $resource = DataImportResource::class;

    protected function getHeaderActions(): array
    {
        return [DataImportResource::errorsAction(), DataImportResource::commitAction()];
    }
}
