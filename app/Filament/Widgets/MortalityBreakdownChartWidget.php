<?php

namespace App\Filament\Widgets;

use App\Domain\Health\Actions\GetMortalityAnalysis;
use App\Filament\Concerns\ValidatesReportPeriod;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

/** The same grouping as the mortality screen drawn as bars: deaths in the chosen period by pen, stage, cause or whichever dimension is selected. */
class MortalityBreakdownChartWidget extends ChartWidget
{
    use ValidatesReportPeriod;

    /** The analysis screen's filters. The page supplies them and keeps the widget in step while they change. */
    public string $dimension = 'stage';

    protected function getType(): string
    {
        return 'bar';
    }

    /** The hosting page changed its grouping or period: follow it. */
    #[On('mortality-analysis-filter-changed')]
    public function applyMortalityFilter(string $dimension, string $periodFrom, string $periodTo): void
    {
        $this->dimension = $dimension;
        $this->from = $periodFrom;
        $this->to = $periodTo;
        $this->cachedData = null;
    }

    public function getHeading(): string
    {
        return 'Deaths by '.str_replace('_', ' ', $this->dimension);
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        if (! in_array($this->dimension, GetMortalityAnalysis::DIMENSIONS, true) || $this->periodErrors() !== []) {
            return [];
        }

        $analysis = app(GetMortalityAnalysis::class)($this->periodStart()->startOfDay(), $this->periodEnd()->endOfDay(), $this->dimension);

        if ($analysis['rows']->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [['label' => 'Deaths', 'data' => $analysis['rows']->pluck('count')->all()]],
            'labels' => $analysis['rows']->pluck('label')->all(),
        ];
    }
}
