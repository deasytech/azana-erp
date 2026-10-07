<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\Actions\GetTargetVsActual;
use App\Domain\Reporting\KpiRegistry;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

/** Each indicator of a dashboard for one month drawn against its target: the share reached, green where the target was met and red where it was missed. */
class TargetAttainmentChartWidget extends ChartWidget
{
    /** The month shown. The page hosting the widget supplies it and may change it while the widget is open. */
    public int $year = 0;

    public int $month = 0;

    public string $area = 'executive';

    protected function getType(): string
    {
        return 'bar';
    }

    /** The hosting page changed its month, year or dashboard: follow it. */
    #[On('report-filter-changed')]
    public function applyReportFilter(int $year, int $month, string $area): void
    {
        $this->year = $year;
        $this->month = $month;
        $this->area = $area;
        $this->cachedData = null;
    }

    public function getHeading(): string
    {
        return 'Against target - '.$this->periodLabel();
    }

    public function getDescription(): string
    {
        return (KpiRegistry::AREAS[$this->area] ?? KpiRegistry::AREAS['executive'])[0].' indicators, share of target reached.';
    }

    /** Horizontal bars, so the indicator names read down the side instead of crowding the base. */
    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $targeted = array_values(array_filter(
            app(GetTargetVsActual::class)($this->chosenYear(), $this->chosenMonth(), auth()->user(), now()),
            fn (array $k): bool => ($this->area === 'executive' ? $k['executive'] : $k['area'] === $this->area) && $k['attainment_percent'] !== null,
        ));

        if ($targeted === []) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Share of target reached',
                'data' => array_map(fn (array $k): float => (float) $k['attainment_percent'], $targeted),
                'backgroundColor' => array_map(fn (array $k): string => match ($k['status']) {
                    'met' => '#16a34a',
                    'missed' => '#dc2626',
                    default => '#9ca3af',
                }, $targeted),
            ]],
            'labels' => array_map(fn (array $k): string => $k['label'], $targeted),
        ];
    }

    /** Client-writable properties are clamped to real values before use, as on the report pages. */
    private function chosenYear(): int
    {
        return max(2000, min(2100, $this->year ?: (int) now()->year));
    }

    private function chosenMonth(): int
    {
        return max(1, min(12, $this->month ?: (int) now()->month));
    }

    private function periodLabel(): string
    {
        return date('F', mktime(0, 0, 0, $this->chosenMonth(), 1)).' '.$this->chosenYear();
    }
}
