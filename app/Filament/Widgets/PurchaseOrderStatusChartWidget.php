<?php

namespace App\Filament\Widgets;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use Filament\Widgets\ChartWidget;

/** Purchase orders by where they stand: how many sit at each status, for the list screen. */
class PurchaseOrderStatusChartWidget extends ChartWidget
{
    protected ?string $heading = 'Orders by status';

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $counts = PurchaseOrder::query()->toBase()
            ->select('status')->selectRaw('count(*) as total')->groupBy('status')
            ->pluck('total', 'status');

        if ($counts->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Orders',
                'data' => $counts->map(fn ($total): int => (int) $total)->values()->all(),
                'backgroundColor' => $counts->keys()->map(fn (string $status): string => self::statusColor(PurchaseOrderStatus::from($status)))->values()->all(),
            ]],
            'labels' => $counts->keys()->map(fn (string $status): string => PurchaseOrderStatus::from($status)->label())->values()->all(),
        ];
    }

    /** The same meanings as the badge colours on the orders table. */
    private static function statusColor(PurchaseOrderStatus $status): string
    {
        return match ($status) {
            PurchaseOrderStatus::Approved, PurchaseOrderStatus::Received => '#16a34a',
            PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::PendingApproval => '#d97706',
            PurchaseOrderStatus::Rejected, PurchaseOrderStatus::Cancelled => '#dc2626',
            default => '#9ca3af',
        };
    }
}
