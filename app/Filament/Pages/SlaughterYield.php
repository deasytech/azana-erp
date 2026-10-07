<?php

namespace App\Filament\Pages;

use App\Domain\Slaughter\Actions\GetSlaughterYield;
use App\Domain\Slaughter\Models\Carcass;
use App\Filament\Concerns\ValidatesReportPeriod;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Dressing percentage and losses over a period, against the farm's target. */
class SlaughterYield extends Page
{
    use ValidatesReportPeriod;

    protected string $view = 'filament.pages.slaughter-yield';

    protected static ?string $navigationLabel = 'Yield report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Slaughter & meat';

    protected static ?int $navigationSort = 40;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Carcass::class) ?? false;
    }

    public function mount(): void
    {
        $this->from = now()->subDays(27)->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return list<string> problems with the chosen period (the properties are client-writable, so they are checked) */
    public function inputErrors(): array
    {
        return $this->periodErrors();
    }

    /** @return ?array<string, mixed> null while the period is invalid */
    public function getReportProperty(): ?array
    {
        return $this->inputErrors() === [] ? app(GetSlaughterYield::class)($this->periodStart(), $this->periodEnd()) : null;
    }
}
