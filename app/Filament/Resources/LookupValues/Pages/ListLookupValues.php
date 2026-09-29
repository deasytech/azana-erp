<?php

namespace App\Filament\Resources\LookupValues\Pages;

use App\Filament\Resources\LookupValues\LookupValueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLookupValues extends ListRecords
{
    protected static string $resource = LookupValueResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
