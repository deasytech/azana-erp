<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Actions\GetTrialBalance;
use App\Domain\Finance\Models\Account;
use App\Domain\Reporting\Exports\ReportDocument;
use App\Domain\Reporting\Exports\ReportDocuments;
use App\Filament\Concerns\OffersReportExport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Every account with its debits, credits and balance. */
class TrialBalanceReport extends Page
{
    use OffersReportExport;

    protected string $view = 'filament.pages.trial-balance-report';

    protected static ?string $navigationLabel = 'Trial balance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 85;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Account::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function getReportProperty(): array
    {
        return app(GetTrialBalance::class)();
    }

    protected function exportPermission(): string
    {
        return 'finance.export';
    }

    protected function reportDocument(): ?ReportDocument
    {
        return app(ReportDocuments::class)->trialBalance($this->report);
    }
}
