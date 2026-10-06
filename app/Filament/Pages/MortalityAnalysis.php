<?php

namespace App\Filament\Pages;

use App\Domain\Health\Actions\GetMortalityAnalysis;
use App\Domain\Health\Models\MortalityRecord;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use UnitEnum;

/** Deaths by pen, stage, age, litter, sow, breed, cause or month over a period. */
class MortalityAnalysis extends Page
{
    protected string $view = 'filament.pages.mortality-analysis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 19;

    /** The longest period the report will cover (a year and a leap day). */
    private const MAX_DAYS = 366;

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

    /** @return list<string> problems with the chosen period or grouping (empty when the input is usable) */
    public function inputErrors(): array
    {
        $errors = Validator::make(
            ['from' => $this->from, 'to' => $this->to, 'dimension' => $this->dimension],
            [
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                'dimension' => ['required', Rule::in(array_keys($this->dimensions()))],
            ],
            ['to.after_or_equal' => 'The end date must be on or after the start date.'],
        )->errors()->all();

        // Only once the dates are valid: an unbounded period would make the report scan every record ever kept.
        if ($errors === [] && Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to)) > self::MAX_DAYS) {
            $errors[] = 'Choose a period of no more than '.self::MAX_DAYS.' days.';
        }

        return $errors;
    }

    /** @return ?array{total: int, rows: Collection<int, array{label: string, count: int, percent: ?string}>} null while the input is invalid */
    public function getAnalysisProperty(): ?array
    {
        if ($this->inputErrors() !== []) {
            return null;
        }

        return app(GetMortalityAnalysis::class)(Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay(), $this->dimension);
    }
}
