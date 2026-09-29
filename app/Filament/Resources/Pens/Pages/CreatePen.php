<?php

namespace App\Filament\Resources\Pens\Pages;

use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Pens\PenResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePen extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = PenResource::class;
}
