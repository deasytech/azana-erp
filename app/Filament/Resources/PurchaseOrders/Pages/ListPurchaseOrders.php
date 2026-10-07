<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Widgets\PurchaseOrderStatusChartWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New order')];
    }

    /** How many orders sit at each status, beside the table. */
    protected function getHeaderWidgets(): array
    {
        return [PurchaseOrderStatusChartWidget::class];
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 3;
    }
}
