<?php

namespace App\Filament\Widgets;

use App\Domain\Breeding\Models\Farrowing;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Piglets born alive and stillborn in each of the last 12 weeks, from the recorded farrowings. */
class BornAliveChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Piglets born, last 12 weeks';

    protected function getType(): string
    {
        return 'bar';
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('breeding.view') ?? false;
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $weeks = collect(range(11, 0))->map(fn (int $n): CarbonImmutable => CarbonImmutable::today()->startOfWeek()->subWeeks($n));
        $rows = Farrowing::query()->toBase()->where('farrowed_on', '>=', $weeks->first()->toDateString())->get(['farrowed_on', 'born_alive', 'stillborn'])
            ->groupBy(fn ($r): string => CarbonImmutable::parse($r->farrowed_on)->startOfWeek()->toDateString());

        if ($rows->isEmpty()) {
            return [];
        }

        $total = fn (string $column): array => $weeks->map(fn ($w): int => (int) ($rows[$w->toDateString()] ?? collect())->sum($column))->all();

        return [
            'datasets' => [
                ['label' => 'Born alive', 'data' => $total('born_alive'), 'backgroundColor' => '#f08732'],
                ['label' => 'Stillborn', 'data' => $total('stillborn'), 'backgroundColor' => '#a8a29e'],
            ],
            'labels' => $weeks->map(fn ($w): string => 'Week of '.$w->format('j M'))->all(),
        ];
    }
}
