<?php

namespace App\Filament\Pages;

use App\Domain\Breeding\Actions\GetBreedingCalendar;
use App\Domain\Breeding\Models\BreedingService;
use App\Filament\Resources\Animals\AnimalResource;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Upcoming breeding events (checks, farrowings, weanings, next services) for a date range. */
class BreedingCalendar extends Page
{
    protected string $view = 'filament.pages.breeding-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Breeding';

    protected static ?int $navigationSort = 5;

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', BreedingService::class) ?? false;
    }

    public function mount(): void
    {
        $this->from = now()->toDateString();
        $this->to = now()->addDays(30)->toDateString();
    }

    /** @return Collection<int, array{date: Carbon, type: string, sow: string, detail: string, sow_id: int}> */
    public function getEntriesProperty(): Collection
    {
        return app(GetBreedingCalendar::class)(Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay());
    }

    public function animalUrl(int $id): string
    {
        return AnimalResource::getUrl('view', ['record' => $id]);
    }
}
