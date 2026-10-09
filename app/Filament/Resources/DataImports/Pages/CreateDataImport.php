<?php

namespace App\Filament\Resources\DataImports\Pages;

use App\Domain\Import\Actions\CheckImport;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\DataImports\DataImportResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateDataImport extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = DataImportResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [DataImportResource::templateActions()];
    }

    protected function getCreateFormActions(): array
    {
        return [parent::getCreateFormActions()[0]->label('Check the file')];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $stored = $data['file'];
        $disk = Storage::disk('local');

        try {
            return app(CheckImport::class)($data['type'], $disk->path($stored), $data['file_name'] ?? basename($stored), auth()->user());
        } catch (DomainException $e) {
            $this->failWith($e);
        } finally {
            $disk->delete($stored);
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return DataImportResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
