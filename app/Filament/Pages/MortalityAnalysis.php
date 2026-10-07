<?php

namespace App\Filament\Pages;

use App\Domain\Health\Actions\GetMortalityAnalysis;
use App\Domain\Health\Models\MortalityRecord;
use App\Filament\Concerns\ValidatesReportPeriod;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use UnitEnum;

/** Deaths by pen, stage, age, litter, sow, breed, cause or month over a period. */
class MortalityAnalysis extends Page
{
    use ValidatesReportPeriod;

    protected string $view = 'filament.pages.mortality-analysis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 19;

    public string $dimension = 'stage';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', MortalityRecord::class) ?? false;
    }

    public function mount(): void
    {
        $this->from = now()->subMonths(3)->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return array<string, string> */
    public function dimensions(): array
    {
        return [
            'stage' => 'Production stage', 'pen' => 'Pen', 'age_band' => 'Age', 'litter' => 'Litter',
            'sow' => 'Sow', 'breed' => 'Breed', 'cause' => 'Cause', 'month' => 'Month',
        ];
    }

    /** @return list<string> problems with the chosen period or grouping (empty when the input is usable) */
    public function inputErrors(): array
    {
        return $this->periodErrors(['dimension' => $this->dimension], ['dimension' => ['required', Rule::in(array_keys($this->dimensions()))]]);
    }

    /** @return ?array{total: int, rows: Collection<int, array{label: string, count: int, percent: ?string}>} null while the input is invalid */
    public function getAnalysisProperty(): ?array
    {
        if ($this->inputErrors() !== []) {
            return null;
        }

        return app(GetMortalityAnalysis::class)($this->periodStart()->startOfDay(), $this->periodEnd()->endOfDay(), $this->dimension);
    }
}
