<?php

namespace App\Filament\Resources\Pens\Pages;

use App\Filament\Resources\Pens\PenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPens extends ListRecords
{
    protected static string $resource = PenResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
