<?php

namespace App\Filament\Resources\ExpenseRecords\Pages;

use App\Filament\Resources\ExpenseRecords\ExpenseRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenseRecords extends ListRecords
{
    protected static string $resource = ExpenseRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Record expense')];
    }
}
