<?php

namespace App\Filament\Widgets;

use App\Domain\Farm\Models\Farm;
use App\Domain\Procurement\Actions\GetSupplierBalances;
use App\Support\Money;
use Filament\Widgets\ChartWidget;

/** What suppliers are owed, the ten largest balances drawn whole with the part already overdue alongside. */
class SupplierBalancesChartWidget extends ChartWidget
{
    protected ?string $heading = 'Outstanding by supplier';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getDescription(): string
    {
        return 'The ten suppliers with the largest balance, in '.Farm::defaultCurrency().'.';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $rows = app(GetSupplierBalances::class)()->take(10);

        if ($rows->isEmpty()) {
            return [];
        }

        $currency = Farm::defaultCurrency();
        $major = fn (int $minor): float => (float) Money::ofMinor($minor, $currency)->toDecimal();

        return [
            'datasets' => [
                ['label' => 'Still owed', 'data' => $rows->pluck('outstanding')->map($major)->all()],
                ['label' => 'Of which overdue', 'data' => $rows->pluck('overdue')->map($major)->all(), 'backgroundColor' => '#dc2626'],
            ],
            'labels' => $rows->pluck('supplier.name')->all(),
        ];
    }
}
