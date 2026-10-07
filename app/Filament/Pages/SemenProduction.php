<?php

namespace App\Filament\Pages;

use App\Domain\Semen\Actions\GetSemenProduction;
use App\Domain\Semen\Models\SemenBatch;
use App\Filament\Concerns\ValidatesReportPeriod;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Collections, QC pass rate and doses per boar over a period, against the weekly targets. */
class SemenProduction extends Page
{
    use ValidatesReportPeriod;

    protected string $view = 'filament.pages.semen-production';

    protected static ?string $navigationLabel = 'Production report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Semen';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', SemenBatch::class) ?? false;
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

    /** @return ?array{rows: Collection, totals: array<string, int|string|null>} null while the period is invalid */
    public function getReportProperty(): ?array
    {
        return $this->inputErrors() === [] ? app(GetSemenProduction::class)($this->periodStart(), $this->periodEnd()) : null;
    }
}
