<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Actions\GetProfitability;
use App\Domain\Finance\Models\Account;
use App\Domain\Reporting\Exports\ReportDocument;
use App\Domain\Reporting\Exports\ReportDocuments;
use App\Filament\Concerns\IsPeriodReport;
use App\Filament\Concerns\OffersReportExport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Margin by business unit over a period. */
class ProfitabilityReport extends Page
{
    use IsPeriodReport;
    use OffersReportExport;

    protected string $view = 'filament.pages.profitability-report';

    protected static ?string $navigationLabel = 'Profitability';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Account::class) ?? false;
    }

    protected function reportAction(): string
    {
        return GetProfitability::class;
    }

    protected function canExport(): bool
    {
        return $this->inputErrors() === [];
    }

    protected function exportPermission(): string
    {
        return 'finance.export';
    }

    protected function reportDocument(): ?ReportDocument
    {
        return ($report = $this->report) ? app(ReportDocuments::class)->profitability($report, $this->periodStart(), $this->periodEnd()) : null;
    }
}
