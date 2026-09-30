<?php

namespace App\Filament\Resources\BiosecurityChecks\Pages;

use App\Filament\Resources\BiosecurityChecks\BiosecurityCheckResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBiosecurityChecks extends ListRecords
{
    protected static string $resource = BiosecurityCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record inspection')];
    }
}
