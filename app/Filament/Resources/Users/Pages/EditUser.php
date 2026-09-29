<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\System\Actions\RecordAudit;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var list<string> */
    private array $rolesBefore = [];

    protected function beforeSave(): void
    {
        $this->rolesBefore = $this->record->roles->pluck('name')->sort()->values()->all();
    }

    protected function afterSave(): void
    {
        $after = $this->record->fresh()->roles->pluck('name')->sort()->values()->all();

        if ($after !== $this->rolesBefore) {
            app(RecordAudit::class)('roles_changed', $this->record, ['roles' => $this->rolesBefore], ['roles' => $after]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
