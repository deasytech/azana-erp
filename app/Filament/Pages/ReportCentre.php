<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\Actions\BuildReport;
use App\Domain\Reporting\Exports\ExportReport;
use App\Domain\Reporting\Exports\ReportDocument;
use App\Domain\Reporting\ReportCatalogue;
use App\Filament\Concerns\ValidatesReportPeriod;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/** Where the business owner runs reports: pick a report, see it (this week, month and year side by side, or over chosen dates) and download it as PDF or Excel. */
class ReportCentre extends Page
{
    use ValidatesReportPeriod;

    protected string $view = 'filament.pages.report-centre';

    protected static ?string $title = 'Report centre';

    protected static ?string $navigationLabel = 'Report centre';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 10;

    public string $mode = BuildReport::GLANCE;

    public ?string $selected = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return array<string, array{title: string, description: string, icon: string, areas: list<string>}> */
    public function getReportsProperty(): array
    {
        return ReportCatalogue::for(auth()->user());
    }

    public function canExport(): bool
    {
        return auth()->user()->can('reports.export');
    }

    public function show(string $key): void
    {
        $this->selected = array_key_exists($key, $this->reports) ? $key : null;
    }

    /** @return list<string> problems with the chosen inputs (the properties are client-writable, so they are checked) */
    public function inputErrors(): array
    {
        if (! in_array($this->mode, [BuildReport::GLANCE, BuildReport::CUSTOM], true)) {
            return ['Choose how to run the report.'];
        }

        return $this->mode === BuildReport::CUSTOM ? $this->periodErrors() : [];
    }

    /** The chosen report as tables, or null while nothing is chosen or the inputs are not valid. */
    public function getDocumentProperty(): ?ReportDocument
    {
        return $this->documentFor($this->selected);
    }

    public function download(string $key, string $format): ?StreamedResponse
    {
        abort_unless($this->canExport(), 403);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $document = $this->documentFor($key);

        if ($document === null) {
            return null;
        }

        $contents = app(ExportReport::class)->{$format}($document);
        $type = ['xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'][$format];

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $document->filename($format), ['Content-Type' => $type]);
    }

    private function documentFor(?string $key): ?ReportDocument
    {
        if ($key === null || ! array_key_exists($key, $this->reports) || $this->inputErrors() !== []) {
            return null;
        }

        $custom = $this->mode === BuildReport::CUSTOM;

        return app(BuildReport::class)($key, $this->mode, $custom ? $this->periodStart() : null, $custom ? $this->periodEnd() : null, auth()->user());
    }
}
