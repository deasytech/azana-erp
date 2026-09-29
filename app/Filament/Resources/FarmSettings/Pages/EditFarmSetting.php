<?php

namespace App\Filament\Resources\FarmSettings\Pages;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\FarmSetting;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\FarmSettings\FarmSettingResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFarmSetting extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = FarmSettingResource::class;

    /** Goes through the domain action so the value is validated against the setting's type. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var FarmSetting $record */
        try {
            return app(ResolveSettings::class)->set($record->key, $data['value'], $record->farm);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
