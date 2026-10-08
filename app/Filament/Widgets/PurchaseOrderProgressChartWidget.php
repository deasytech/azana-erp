<?php

namespace App\Filament\Widgets;

use App\Domain\Farm\Models\Farm;
use App\Domain\Procurement\Actions\GetPurchaseTrace;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Support\Money;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

/** One order on the road from ordering to payment: what was ordered, received, invoiced and paid, drawn from the same trace the order page shows. */
class PurchaseOrderProgressChartWidget extends ChartWidget
{
    /** The order this page has open. ViewRecord supplies it with the widget. */
    public ?PurchaseOrder $record = null;

    /** The order changed on the page (a receipt recorded, an invoice paid): read it again. */
    #[On('purchase-order-changed')]
    public function refreshOrder(): void
    {
        $this->cachedData = null;
    }

    protected ?string $heading = 'Progress to payment';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->record === null ? null : "{$this->record->number} from {$this->record->supplier->name}";
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        if ($this->record === null) {
            return [];
        }

        $currency = Farm::defaultCurrency();
        $major = fn (int $minor): float => (float) Money::ofMinor($minor, $currency)->toDecimal();
        $trace = app(GetPurchaseTrace::class)($this->record);

        return [
            'datasets' => [['label' => 'Value', 'data' => array_map($major, [
                (int) $this->record->total_minor, $trace['received_value'], $trace['invoiced'], $trace['paid'],
            ])]],
            'labels' => ['Ordered', 'Received', 'Invoiced', 'Paid'],
        ];
    }
}
