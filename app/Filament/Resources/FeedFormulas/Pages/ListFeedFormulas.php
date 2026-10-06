<?php

namespace App\Filament\Resources\FeedFormulas\Pages;

use App\Filament\Resources\FeedFormulas\FeedFormulaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeedFormulas extends ListRecords
{
    protected static string $resource = FeedFormulaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New formula')];
    }
}
