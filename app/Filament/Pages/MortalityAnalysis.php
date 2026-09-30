<?php

namespace App\Filament\Pages;

use App\Domain\Health\Actions\GetMortalityAnalysis;
use App\Domain\Health\Models\MortalityRecord;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Deaths by pen, stage, age, litter, sow, breed, cause or month over a period. */
class MortalityAnalysis extends Page
{
    protected string $view = 'filament.pages.mortality-analysis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 19;

    public string $dimension = 'stage';

    public string $from = '';

    public string $to = '';

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

    /** @return array{total: int, rows: Collection<int, array{label: string, count: int, percent: ?string}>} */
    public function getAnalysisProperty(): array
    {
        return app(GetMortalityAnalysis::class)(Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay(), $this->dimension);
    }
}
