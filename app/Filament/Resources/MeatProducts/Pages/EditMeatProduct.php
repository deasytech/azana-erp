<?php

namespace App\Filament\Resources\MeatProducts\Pages;

use App\Filament\Resources\MeatProducts\MeatProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMeatProduct extends EditRecord
{
    protected static string $resource = MeatProductResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
