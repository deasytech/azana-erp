<?php

namespace App\Filament\Concerns;

use App\Domain\Reporting\Exports\ExportReport;
use App\Domain\Reporting\Exports\ReportDocument;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Export buttons (CSV, Excel, PDF) for a report page, for people holding the page's export permission. The page says what the document is. */
trait OffersReportExport
{
    /** @return ?ReportDocument null while there is nothing valid to export */
    abstract protected function reportDocument(): ?ReportDocument;

    /** @return string the permission that allows exporting, e.g. finance.export */
    abstract protected function exportPermission(): string;

    protected function getHeaderActions(): array
    {
        $button = fn (string $format, string $label) => Action::make($format)->label($label)->action(fn () => $this->download($format));

        return [
            ActionGroup::make([$button('csv', 'CSV'), $button('xlsx', 'Excel'), $button('pdf', 'PDF')])->label('Export')->icon('heroicon-o-arrow-down-tray')->button()
                ->visible(fn () => auth()->user()->can($this->exportPermission()) && $this->reportDocument() !== null),
        ];
    }

    public function download(string $format): ?StreamedResponse
    {
        abort_unless(auth()->user()->can($this->exportPermission()), 403);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $document = $this->reportDocument();

        if ($document === null) {
            return null;
        }

        $contents = app(ExportReport::class)->{$format}($document);

        return response()->streamDownload(fn () => print ($contents), $document->filename($format), ['Content-Type' => ['csv' => 'text/csv; charset=UTF-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'][$format]]);
    }
}
