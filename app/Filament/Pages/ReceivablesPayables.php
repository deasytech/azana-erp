<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Actions\GetReceivablesPayables;
use App\Domain\Finance\Models\Account;
use App\Domain\Reporting\Exports\ReportDocument;
use App\Domain\Reporting\Exports\ReportDocuments;
use App\Filament\Concerns\OffersReportExport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** What customers owe and what is owed to suppliers, aged by days past due. */
class ReceivablesPayables extends Page
{
    use OffersReportExport;

    protected string $view = 'filament.pages.receivables-payables';

    protected static ?string $navigationLabel = 'Receivables & payables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 80;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Account::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function getReportProperty(): array
    {
        return app(GetReceivablesPayables::class)();
    }

    protected function exportPermission(): string
    {
        return 'finance.export';
    }

    protected function reportDocument(): ?ReportDocument
    {
        return app(ReportDocuments::class)->ageing($this->report, now());
    }
}
