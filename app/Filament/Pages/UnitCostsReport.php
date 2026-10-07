<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Actions\GetUnitCosts;
use App\Domain\Finance\Models\Account;
use App\Filament\Concerns\ValidatesReportPeriod;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** What a pig, a kg of feed, a dose of semen and a kg of meat cost. */
class UnitCostsReport extends Page
{
    use ValidatesReportPeriod;

    protected string $view = 'filament.pages.unit-costs-report';

    protected static ?string $navigationLabel = 'Unit costs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 70;

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
        return $this->inputErrors() === [] ? app(GetUnitCosts::class)($this->periodStart(), $this->periodEnd()) : null;
    }
}
