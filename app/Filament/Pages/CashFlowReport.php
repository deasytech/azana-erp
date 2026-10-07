<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Actions\GetCashFlow;
use App\Domain\Finance\Models\Account;
use App\Filament\Concerns\IsPeriodReport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Cash in and out of the cash and bank accounts over a period. */
class CashFlowReport extends Page
{
    use IsPeriodReport;

    protected string $view = 'filament.pages.cash-flow-report';

    protected static ?string $navigationLabel = 'Cash flow';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 60;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Account::class) ?? false;
    }

    protected function reportAction(): string
    {
        return GetCashFlow::class;
    }
}
