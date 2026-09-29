<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\Concerns\HandlesPermissionMatrix;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    use HandlesPermissionMatrix;

    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractMatrix($data) + ['guard_name' => 'web'];
    }

    protected function afterCreate(): void
    {
        $this->syncMatrix($this->record);
    }
}
