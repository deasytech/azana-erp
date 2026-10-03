<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\Actions\GetExpiryAlerts;
use App\Domain\Inventory\Actions\GetReorderAlerts;
use App\Domain\Inventory\Models\InventoryTransaction;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Items to reorder and batches that are expired or about to expire. */
class StockAlerts extends Page
{
    protected string $view = 'filament.pages.stock-alerts';

    protected static ?string $navigationLabel = 'Stock alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', InventoryTransaction::class) ?? false;
    }

    public function getReorderProperty(): Collection
    {
        return app(GetReorderAlerts::class)();
    }

    public function getExpiryProperty(): Collection
    {
        return app(GetExpiryAlerts::class)();
    }
}
