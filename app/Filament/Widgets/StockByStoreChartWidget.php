<?php

namespace App\Filament\Widgets;

use App\Domain\Farm\Models\Farm;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryLayer;
use App\Support\Money;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

/** The value of stock on hand split by store, or by item when one store is chosen: the same rows as the stock screen, filtered with it. */
class StockByStoreChartWidget extends ChartWidget
{
    /** The stock screen's filters. The page supplies them and keeps the widget in step while they change. */
    public ?int $item = null;

    public ?int $location = null;

    protected ?string $maxHeight = '20rem';

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** The hosting page changed its item or store filter: follow it. */
    #[On('stock-overview-filter-changed')]
    public function applyStockFilter(?int $item, ?int $location): void
    {
        $this->item = $item;
        $this->location = $location;
        $this->cachedData = null;
    }

    public function getHeading(): string
    {
        return $this->location !== null ? 'Stock value by item in this store' : 'Stock value by store';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $levels = app(GetStockLevels::class)($this->item, $this->location);

        if ($levels->isEmpty()) {
            return [];
        }

        $currency = Farm::defaultCurrency();
        $groupedLevels = $levels
            ->groupBy(fn (InventoryLayer $row): int => $this->location !== null ? $row->item->id : $row->location->id);
        $values = $groupedLevels
            ->map(fn ($rows): float => (float) Money::ofMinor((int) $rows->sum('value_minor'), $currency)->toDecimal());
        $labels = $groupedLevels
            ->map(fn ($rows): string => $this->location !== null ? $rows->first()->item->name : $rows->first()->location->name);

        return [
            'datasets' => [['label' => 'Value', 'data' => $values->values()->all()]],
            'labels' => $labels->values()->all(),
        ];
    }
}
