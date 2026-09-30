<?php

namespace App\Filament\Resources\VaccinationSchedules\Pages;

use App\Filament\Resources\VaccinationSchedules\VaccinationScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVaccinationSchedule extends EditRecord
{
    protected static string $resource = VaccinationScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
