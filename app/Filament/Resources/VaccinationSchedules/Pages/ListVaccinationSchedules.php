<?php

namespace App\Filament\Resources\VaccinationSchedules\Pages;

use App\Filament\Resources\VaccinationSchedules\VaccinationScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVaccinationSchedules extends ListRecords
{
    protected static string $resource = VaccinationScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
