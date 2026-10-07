<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Actions\GetUnitCosts;
use App\Domain\Finance\Models\Account;
use App\Filament\Concerns\IsPeriodReport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** What a pig, a kg of feed, a dose of semen and a kg of meat cost. */
class UnitCostsReport extends Page
{
    use IsPeriodReport;

    protected string $view = 'filament.pages.unit-costs-report';

    protected static ?string $navigationLabel = 'Unit costs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 70;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Account::class) ?? false;
    }

    protected function reportAction(): string
    {
        return GetUnitCosts::class;
    }
}
