<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\System\Actions\RecordAudit;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        app(RecordAudit::class)('roles_assigned', $this->record, null, ['roles' => $this->record->roles->pluck('name')->all()]);
    }
}
