<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Reporting\Models\KpiTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Each indicator for one calendar month against its target. A target is the one set for that month, else the one for the year, else
 * the farm setting that backs the indicator (weaning, dressing, mortality), else none. Status: met / missed (by which way is good for
 * the indicator), or none when there is no target or no value. Attainment is actual as a percentage of target.
 */
class GetTargetVsActual
{
    public function __construct(private readonly GetKpis $kpis, private readonly ResolveSettings $settings) {}

    /** @return array<string, array<string, mixed>> */
    public function __invoke(int $year, int $month, ?User $for = null, ?Carbon $asAt = null): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();
        // The month in progress is measured up to today; levels (stock, owed) are as at the end of the period.
        $to = $asAt && $asAt->between($from, $to) ? $asAt->copy()->endOfDay() : $to;

        $targets = $this->targetsFor($year, $month);

        return array_map(function (array $k) use ($targets) {
            $target = $targets[$k['key']]->target_value ?? ($k['setting'] ? (string) $this->settings->get($k['setting']) : null);

            return $k + [
                'target' => $target !== null ? $this->trim((string) $target) : null,
                'status' => $this->status($k['better'], $k['value'], $target),
                'attainment_percent' => $this->attainment($k['value'], $target),
            ];
        }, ($this->kpis)($from, $to, $for));
    }

    /** @return Collection<string, KpiTarget> the target in force for each indicator: the month's own, else the year's */
    private function targetsFor(int $year, int $month): Collection
    {
        return KpiTarget::where('year', $year)->where(fn ($q) => $q->where('month', $month)->orWhereNull('month'))->get()
            ->sortByDesc(fn (KpiTarget $t) => $t->month ?? 0)->unique('kpi_key')->keyBy('kpi_key');
    }

    private function status(string $better, int|string|null $value, int|string|null $target): string
    {
        if ($target === null || $value === null || $better === 'none') {
            return 'none';
        }

        $order = bccomp((string) $value, (string) $target, 4);

        return ($better === 'higher' ? $order >= 0 : $order <= 0) ? 'met' : 'missed';
    }

    /** Actual as a percentage of target, to one decimal. */
    private function attainment(int|string|null $value, int|string|null $target): ?string
    {
        if ($target === null || $value === null || bccomp((string) $target, '0', 4) === 0) {
            return null;
        }

        return bcmul(bcdiv((string) $value, (string) $target, 4), '100', 1);
    }

    /** 12.5000 -> 12.5, 90.0000 -> 90 */
    private function trim(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
