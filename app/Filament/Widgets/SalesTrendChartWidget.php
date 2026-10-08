<?php

namespace App\Filament\Widgets;

use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Sales invoiced and money received each day for the last 30 days, in the farm currency (the same sources as the sales indicators). */
class SalesTrendChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Sales and receipts, last 30 days';

    protected function getType(): string
    {
        return 'line';
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('sales.view') ?? false;
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn (int $n): CarbonImmutable => CarbonImmutable::today()->subDays($n));
        $from = $days->first()->toDateString();
        $sum = fn ($query, string $date, string $amount) => $query->toBase()->whereBetween($date, [$from, now()->toDateString()])
            ->selectRaw("{$date} as day, sum({$amount}) as total")->groupBy($date)->get()
            ->mapWithKeys(fn ($r): array => [substr((string) $r->day, 0, 10) => (int) $r->total]);

        $invoiced = $sum(Invoice::query(), 'issued_on', 'total_minor');
        $received = $sum(Payment::query()->whereNull('voided_at'), 'received_on', 'amount_minor');

        if ($invoiced->isEmpty() && $received->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                ['label' => 'Invoiced', 'data' => $days->map(fn ($d): float => ($invoiced[$d->toDateString()] ?? 0) / 100)->all(), 'borderColor' => '#f08732', 'backgroundColor' => 'rgba(240,135,50,0.12)', 'fill' => true, 'tension' => 0.3],
                ['label' => 'Received', 'data' => $days->map(fn ($d): float => ($received[$d->toDateString()] ?? 0) / 100)->all(), 'borderColor' => '#16a34a', 'tension' => 0.3],
            ],
            'labels' => $days->map(fn ($d): string => $d->format('j M'))->all(),
        ];
    }
}
