<?php

namespace App\Filament\Pages;

use App\Domain\Procurement\Actions\GetSupplierBalances;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Filament\Support\MoneyColumn;
use App\Filament\Widgets\SupplierBalancesChartWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** What each supplier has invoiced, been paid and is still owed. */
class SupplierBalances extends Page
{
    protected string $view = 'filament.pages.supplier-balances';

    protected static ?string $navigationLabel = 'Supplier balances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', SupplierInvoice::class) ?? false;
    }

    public function getBalancesProperty(): Collection
    {
        return app(GetSupplierBalances::class)();
    }

    public function money(int $minor): string
    {
        return MoneyColumn::format($minor);
    }

    /** The same balances drawn as bars, below the table. */
    protected function getFooterWidgets(): array
    {
        return [SupplierBalancesChartWidget::class];
    }

    public function getFooterWidgetsColumns(): int
    {
        return 1;
    }
}
