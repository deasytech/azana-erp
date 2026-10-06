<?php

namespace App\Filament\Resources\SemenBoars\Pages;

use App\Filament\Resources\SemenBoars\SemenBoarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSemenBoars extends ListRecords
{
    protected static string $resource = SemenBoarResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add boar')];
    }
}
