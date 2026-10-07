<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Actions\GetCashFlow;
use App\Domain\Finance\Models\Account;
use App\Filament\Concerns\ValidatesReportPeriod;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Cash in and out of the cash and bank accounts over a period. */
class CashFlowReport extends Page
{
    use ValidatesReportPeriod;

    protected string $view = 'filament.pages.cash-flow-report';

    protected static ?string $navigationLabel = 'Cash flow';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 60;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Account::class) ?? false;
    }

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return list<string> problems with the chosen period (the properties are client-writable, so they are checked) */
    public function inputErrors(): array
    {
        return $this->periodErrors();
    }

    /** @return ?array<string, mixed> null while the period is invalid */
    public function getReportProperty(): ?array
    {
        return $this->inputErrors() === [] ? app(GetCashFlow::class)($this->periodStart(), $this->periodEnd()) : null;
    }
}
