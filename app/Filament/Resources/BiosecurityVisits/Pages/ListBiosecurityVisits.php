<?php

namespace App\Filament\Resources\BiosecurityVisits\Pages;

use App\Filament\Resources\BiosecurityVisits\BiosecurityVisitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBiosecurityVisits extends ListRecords
{
    protected static string $resource = BiosecurityVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Sign in visitor')];
    }
}
