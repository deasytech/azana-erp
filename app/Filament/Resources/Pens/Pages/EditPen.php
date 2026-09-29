<?php

namespace App\Filament\Resources\Pens\Pages;

use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Pens\PenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPen extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = PenResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
