<?php

namespace App\Filament\Pages;

use App\Domain\Finance\Actions\GetBudgetVsActual;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Budget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** A budget against what the ledger shows, up to a chosen month. */
class BudgetVsActualReport extends Page
{
    public ?int $budgetId = null;

    public ?int $month = null;

    protected string $view = 'filament.pages.budget-vs-actual-report';

    protected static ?string $navigationLabel = 'Budget vs actual';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 55;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Account::class) ?? false;
    }

    public function mount(): void
    {
        $this->budgetId = Budget::orderByDesc('fiscal_year')->value('id');
    }

    /** @return array<int, string> */
    public function budgets(): array
    {
        return Budget::orderByDesc('fiscal_year')->get()->mapWithKeys(fn (Budget $b) => [$b->id => "{$b->name} ({$b->fiscal_year})"])->all();
    }

    /** @return ?array<string, mixed> */
    public function getReportProperty(): ?array
    {
        $budget = $this->budgetId ? Budget::find($this->budgetId) : null;

        return $budget ? app(GetBudgetVsActual::class)($budget, $this->month ? (int) $this->month : null) : null;
    }
}
