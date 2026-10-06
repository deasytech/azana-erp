<?php

namespace App\Filament\Pages;

use App\Domain\Sales\Actions\GetOutstandingBalances;
use App\Domain\Sales\Models\Invoice;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\MoneyColumn;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Every customer who owes money or holds a deposit. */
class OutstandingBalances extends Page
{
    protected string $view = 'filament.pages.outstanding-balances';

    protected static ?string $navigationLabel = 'Outstanding balances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Invoice::class) ?? false;
    }

    public function getBalancesProperty(): Collection
    {
        return app(GetOutstandingBalances::class)();
    }

    public function money(int $minor): string
    {
        return MoneyColumn::format($minor);
    }

    public function customerUrl(int $id): string
    {
        return CustomerResource::getUrl('view', ['record' => $id]);
    }
}
