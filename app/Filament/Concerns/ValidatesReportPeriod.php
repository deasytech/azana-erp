<?php

namespace App\Filament\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * The "from" and "to" dates of a report page. They are client-writable Livewire properties, so they are checked before use:
 * both must be real dates in order, and the period at most a year and a leap day, or the report would scan every record ever kept.
 */
trait ValidatesReportPeriod
{
    public string $from = '';

    public string $to = '';

    /** The longest period a report will cover. */
    private const MAX_PERIOD_DAYS = 366;

    /**
     * @param  array<string, mixed>  $otherInput  further inputs of the page to validate with the period
     * @param  array<string, mixed>  $otherRules
     * @return list<string> problems with the input (empty when it is usable)
     */
    protected function periodErrors(array $otherInput = [], array $otherRules = []): array
    {
        $errors = Validator::make(['from' => $this->from, 'to' => $this->to] + $otherInput, [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ] + $otherRules, ['to.after_or_equal' => 'The end date must be on or after the start date.'])->errors()->all();

        // Only once the dates themselves are valid.
        if ($errors === [] && $this->periodStart()->diffInDays($this->periodEnd()) > self::MAX_PERIOD_DAYS) {
            $errors[] = 'Choose a period of no more than '.self::MAX_PERIOD_DAYS.' days.';
        }

        return $errors;
    }

    protected function periodStart(): Carbon
    {
        return Carbon::parse($this->from);
    }

    protected function periodEnd(): Carbon
    {
        return Carbon::parse($this->to);
    }
}
