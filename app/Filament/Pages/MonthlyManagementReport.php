<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\Actions\GetMonthlyReport;
use App\Domain\Reporting\Exports\ReportDocument;
use App\Domain\Reporting\Exports\ReportDocuments;
use App\Filament\Concerns\OffersReportExport;
use App\Filament\Concerns\SelectsReportMonth;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** The monthly management report: indicators against target, profit by unit, cash, what is owed, the plan, tasks and alerts. */
class MonthlyManagementReport extends Page
{
    use OffersReportExport;
    use SelectsReportMonth;

    protected string $view = 'filament.pages.monthly-management-report';

    protected static ?string $navigationLabel = 'Monthly report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function mount(): void
    {
        $this->mountSelectsReportMonth();
    }

    /** @return array<string, mixed> */
    public function getReportProperty(): array
    {
        return app(GetMonthlyReport::class)($this->chosenYear(), $this->chosenMonth(), auth()->user());
    }

    protected function exportPermission(): string
    {
        return 'reports.export';
    }

    protected function reportDocument(): ?ReportDocument
    {
        return app(ReportDocuments::class)->monthly($this->report);
    }
}
