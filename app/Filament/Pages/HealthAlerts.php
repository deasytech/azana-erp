<?php

namespace App\Filament\Pages;

use App\Domain\Health\Actions\GetHealthAlerts;
use App\Domain\Health\Models\HealthEvent;
use App\Filament\Resources\Animals\AnimalResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Everything in health that needs attention now. */
class HealthAlerts extends Page
{
    protected string $view = 'filament.pages.health-alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', HealthEvent::class) ?? false;
    }

    /** @return Collection<int, array{severity: string, type: string, message: string, animal_id: ?int}> */
    public function getAlertsProperty(): Collection
    {
        return app(GetHealthAlerts::class)();
    }

    public function animalUrl(int $id): string
    {
        return AnimalResource::getUrl('view', ['record' => $id]);
    }
}
