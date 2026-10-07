<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Reporting\Models\KpiTarget;
use App\Models\User;
use Carbon\Carbon;

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

        $targets = KpiTarget::where('year', $year)->where(fn ($q) => $q->where('month', $month)->orWhereNull('month'))->get()
            ->sortByDesc(fn (KpiTarget $t) => $t->month ?? 0)->unique('kpi_key')->keyBy('kpi_key');

        return array_map(function (array $k) use ($targets) {
            $target = $targets[$k['key']]->target_value ?? ($k['setting'] ? (string) $this->settings->get($k['setting']) : null);
            $value = $k['value'];
            $known = $target !== null && $value !== null && $k['better'] !== 'none';

            return $k + [
                'target' => $target !== null ? $this->trim((string) $target) : null,
                'status' => ! $known ? 'none' : (($k['better'] === 'higher' ? bccomp((string) $value, (string) $target, 4) >= 0 : bccomp((string) $value, (string) $target, 4) <= 0) ? 'met' : 'missed'),
                'attainment_percent' => $target !== null && $value !== null && bccomp((string) $target, '0', 4) !== 0 ? bcmul(bcdiv((string) $value, (string) $target, 4), '100', 1) : null,
            ];
        }, ($this->kpis)($from, $to, $for));
    }

    /** 12.5000 -> 12.5, 90.0000 -> 90 */
    private function trim(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
