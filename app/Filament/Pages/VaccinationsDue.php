<?php

namespace App\Filament\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Actions\GetVaccinationsDue;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Health\Models\VaccinationSchedule;
use App\Filament\Resources\Animals\AnimalResource;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Vaccination reminders: what is due or overdue within the farm's reminder window. */
class VaccinationsDue extends Page
{
    protected string $view = 'filament.pages.vaccinations-due';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Vaccinations due';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Vaccination::class) ?? false;
    }

    /** @return Collection<int, array{animal: Animal, schedule: VaccinationSchedule, due_on: CarbonInterface, overdue: bool}> */
    public function getDueProperty(): Collection
    {
        return app(GetVaccinationsDue::class)();
    }

    public function animalUrl(int $id): string
    {
        return AnimalResource::getUrl('view', ['record' => $id]);
    }
}
