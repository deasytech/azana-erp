<?php

namespace App\Filament\Widgets;

use App\Domain\Health\Actions\GetMortalityAnalysis;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;
use Throwable;

/** The same grouping as the mortality screen drawn as bars: deaths in the chosen period by pen, stage, cause or whichever dimension is selected. */
class MortalityBreakdownChartWidget extends ChartWidget
{
    /** The analysis screen's filters. The page supplies them and keeps the widget in step while they change. */
    public string $dimension = 'stage';

    public string $from = '';

    public string $to = '';

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
        if (! in_array($this->dimension, GetMortalityAnalysis::DIMENSIONS, true) || ! $this->periodIsUsable()) {
            return [];
        }

        $analysis = app(GetMortalityAnalysis::class)(Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay(), $this->dimension);

        if ($analysis['rows']->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [['label' => 'Deaths', 'data' => $analysis['rows']->pluck('count')->all()]],
            'labels' => $analysis['rows']->pluck('label')->all(),
        ];
    }

    /** The dates arrive as client-writable strings, so they are checked before the report is run. */
    private function periodIsUsable(): bool
    {
        try {
            $from = Carbon::parse($this->from);
            $to = Carbon::parse($this->to);
        } catch (Throwable) {
            return false;
        }

        return $from->lessThanOrEqualTo($to) && $from->diffInDays($to) <= 366;
    }
}
