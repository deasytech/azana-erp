<?php

namespace App\Filament\Resources\Culling\Pages;

use App\Filament\Resources\Culling\CullingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCullings extends ListRecords
{
    protected static string $resource = CullingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Cull animal')];
    }
}
