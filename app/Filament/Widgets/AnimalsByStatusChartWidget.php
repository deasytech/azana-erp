<?php

namespace App\Filament\Widgets;

use App\Domain\Animal\Models\Animal;
use App\Enums\AnimalStatus;
use Filament\Widgets\ChartWidget;

/** Every animal ever recorded by where it stands now, coloured as the status badges are. */
class AnimalsByStatusChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Animals by status';

    protected function getType(): string
    {
        return 'bar';
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('animals.view') ?? false;
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]]];
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $counts = Animal::query()->toBase()->select('status')->selectRaw('count(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->isEmpty()) {
            return [];
        }

        $statuses = collect(AnimalStatus::cases())->filter(fn (AnimalStatus $s): bool => $counts->has($s->value));
        $color = ['success' => '#16a34a', 'danger' => '#dc2626', 'warning' => '#d97706', 'info' => '#2563eb', 'gray' => '#a8a29e'];

        return [
            'datasets' => [[
                'label' => 'Animals',
                'data' => $statuses->map(fn (AnimalStatus $s): int => (int) $counts[$s->value])->values()->all(),
                'backgroundColor' => $statuses->map(fn (AnimalStatus $s): string => $color[$s->color()])->values()->all(),
            ]],
            'labels' => $statuses->map(fn (AnimalStatus $s): string => $s->label())->values()->all(),
        ];
    }
}
