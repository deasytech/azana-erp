<?php

namespace App\Filament\Resources\MeatProducts\Pages;

use App\Filament\Resources\MeatProducts\MeatProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeatProducts extends ListRecords
{
    protected static string $resource = MeatProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
