<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\Actions\GetTargetVsActual;
use App\Domain\Reporting\Exports\ReportDocument;
use App\Domain\Reporting\Exports\ReportDocuments;
use App\Filament\Concerns\OffersReportExport;
use App\Filament\Concerns\SelectsReportMonth;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Every indicator for a month against its target, grouped by area. */
class TargetVsActual extends Page
{
    use OffersReportExport;
    use SelectsReportMonth;

    protected string $view = 'filament.pages.target-vs-actual';

    protected static ?string $navigationLabel = 'Target vs actual';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function mount(): void
    {
        $this->mountSelectsReportMonth();
    }

    /** @return array<string, array<string, mixed>> */
    public function getKpisProperty(): array
    {
        return app(GetTargetVsActual::class)($this->chosenYear(), $this->chosenMonth(), auth()->user(), now());
    }

    public function periodLabel(): string
    {
        return date('F', mktime(0, 0, 0, $this->chosenMonth(), 1)).' '.$this->chosenYear();
    }

    protected function exportPermission(): string
    {
        return 'reports.export';
    }

    protected function reportDocument(): ?ReportDocument
    {
        return app(ReportDocuments::class)->targetVsActual($this->kpis, $this->periodLabel());
    }
}
