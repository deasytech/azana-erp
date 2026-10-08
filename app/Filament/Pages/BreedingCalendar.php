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

    /** The quick ranges offered beside the dates: from today for this many days. */
    public function range(int $days): void
    {
        $this->from = now()->toDateString();
        $this->to = now()->addDays(max(1, min($days, 365)))->toDateString();
    }

    /** What is wrong with the chosen dates, if anything (a cleared date or an end before the start); the list is empty until it is fixed. */
    public function inputError(): ?string
    {
        $from = rescue(fn () => Carbon::parse($this->from), null, false);
        $to = rescue(fn () => Carbon::parse($this->to), null, false);

        return match (true) {
            blank($this->from) || blank($this->to) || ! $from || ! $to => 'Choose both a start and an end date.',
            $to->lt($from) => 'The end date is before the start date.',
            default => null,
        };
    }

    /** @return Collection<int, array{date: Carbon, type: string, sow: string, detail: string, sow_id: int}> */
    public function getEntriesProperty(): Collection
    {
        if ($this->inputError()) {
            return collect();
        }

        return app(GetBreedingCalendar::class)(Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay());
    }

    /**
     * How each kind of event is shown: a colour and an icon, so a long list can be scanned by type.
     *
     * @return array{color: string, icon: Heroicon}
     */
    public static function style(string $type): array
    {
        return match ($type) {
            'Pregnancy check due' => ['color' => 'info', 'icon' => Heroicon::OutlinedMagnifyingGlass],
            'Watch for return to heat' => ['color' => 'warning', 'icon' => Heroicon::OutlinedEye],
            'Farrowing expected' => ['color' => 'success', 'icon' => Heroicon::OutlinedSparkles],
            'Weaning due' => ['color' => 'primary', 'icon' => Heroicon::OutlinedScale],
            default => ['color' => 'gray', 'icon' => Heroicon::OutlinedArrowPath],
        };
    }

    public function animalUrl(int $id): string
    {
        return AnimalResource::getUrl('view', ['record' => $id]);
    }
}
