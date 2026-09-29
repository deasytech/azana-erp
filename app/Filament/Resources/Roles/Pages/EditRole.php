<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\Concerns\HandlesPermissionMatrix;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    use HandlesPermissionMatrix;

    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillMatrix($this->record, $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractMatrix($data);
    }

    protected function afterSave(): void
    {
        $this->syncMatrix($this->record);
    }
}
