<?php

namespace App\Filament\Widgets;

use App\Domain\Animal\Models\Animal;
use App\Enums\AnimalStatus;
use Filament\Widgets\ChartWidget;

/** The animals on the farm now, split by category (sows, boars, gilts, growers and so on). */
class HerdByCategoryChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Herd by category';

    protected function getType(): string
    {
        return 'doughnut';
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('animals.view') ?? false;
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $rows = Animal::query()->where('status', AnimalStatus::Active)->with('category:id,name')->get(['id', 'category_id'])
            ->groupBy(fn (Animal $a): string => $a->category?->name ?? 'Uncategorised')->map->count()->sortDesc();

        if ($rows->isEmpty()) {
            return [];
        }

        $palette = ['#f08732', '#f3c63c', '#16a34a', '#2563eb', '#a8a29e', '#7c3aed', '#dc2626', '#0d9488'];

        return [
            'datasets' => [['label' => 'Animals', 'data' => $rows->values()->all(), 'backgroundColor' => array_slice(array_pad($palette, $rows->count(), '#d6d3d1'), 0, $rows->count())]],
            'labels' => $rows->keys()->all(),
        ];
    }
}
