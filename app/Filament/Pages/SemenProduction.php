<?php

namespace App\Filament\Pages;

use App\Domain\Semen\Actions\GetSemenProduction;
use App\Domain\Semen\Models\SemenBatch;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use UnitEnum;

/** Collections, QC pass rate and doses per boar over a period, against the weekly targets. */
class SemenProduction extends Page
{
    protected string $view = 'filament.pages.semen-production';

    protected static ?string $navigationLabel = 'Production report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Semen';

    protected static ?int $navigationSort = 30;

    /** The longest period the report will cover (a year and a leap day). */
    private const MAX_DAYS = 366;

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', SemenBatch::class) ?? false;
    }

    public function mount(): void
    {
        $this->from = now()->subDays(27)->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return list<string> problems with the chosen period (the properties are client-writable, so they are checked) */
    public function inputErrors(): array
    {
        $errors = Validator::make(['from' => $this->from, 'to' => $this->to], [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], ['to.after_or_equal' => 'The end date must be on or after the start date.'])->errors()->all();

        // Only once the dates are valid: an unbounded period would make the report scan every record ever kept.
        if ($errors === [] && Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to)) > self::MAX_DAYS) {
            $errors[] = 'Choose a period of no more than '.self::MAX_DAYS.' days.';
        }

        return $errors;
    }

    /** @return ?array{rows: Collection, totals: array<string, int|string|null>} null while the period is invalid */
    public function getReportProperty(): ?array
    {
        return $this->inputErrors() === [] ? app(GetSemenProduction::class)(Carbon::parse($this->from), Carbon::parse($this->to)) : null;
    }
}
