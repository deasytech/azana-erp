<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Domain\System\Exceptions\DomainException;
use App\Domain\Tasks\Actions\CreateTask as MakeTask;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Tasks\TaskResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTask extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = TaskResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(MakeTask::class)($data['title'], Carbon::parse($data['due_on']), TaskCategory::from($data['category']), TaskPriority::from($data['priority']), $data['description'] ?? null,
                filled($data['assigned_to'] ?? null) ? (int) $data['assigned_to'] : null, $data['responsible_role'] ?? null, null, (bool) ($data['requires_evidence'] ?? false));
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return TaskResource::getUrl('view', ['record' => $this->record]);
    }
}
