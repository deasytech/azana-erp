<?php

namespace App\Domain\Slaughter\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Slaughter\Models\Carcass;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Dressing percentage and losses over a period, against the farm's target, and the carcasses that fell below the alert level. */
class GetSlaughterYield
{
    public function __construct(private readonly ResolveSettings $settings) {}

    /** @return array{totals: array<string, int|string|null>, by_day: Collection<int, array<string, int|string|null>>, low: Collection<int, Carcass>} */
    public function __invoke(CarbonInterface $from, CarbonInterface $to): array
    {
        $carcasses = Carcass::with('record.batch')->whereBetween('slaughtered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();
        $summary = fn (Collection $set) => [
            'heads' => (int) $set->sum(fn (Carcass $c) => $c->record->heads),
            'live_kg' => $live = $set->reduce(fn (string $s, Carcass $c) => bcadd($s, (string) $c->live_weight_kg, 2), '0'),
            'hot_kg' => $hot = $set->reduce(fn (string $s, Carcass $c) => bcadd($s, (string) $c->hot_weight_kg, 2), '0'),
            'condemned_kg' => $set->reduce(fn (string $s, Carcass $c) => bcadd($s, (string) $c->condemned_kg, 2), '0'),
            'dressing_percent' => bccomp($live, '0', 2) > 0 ? Ratio::percent($hot, $live, 2) : null,
        ];
        $alert = (string) $this->settings->get('slaughter.min_dressing_percent_alert');

        return [
            'totals' => $summary($carcasses) + ['target_percent' => (string) $this->settings->get('production.target_dressing_percent'), 'carcasses' => $carcasses->count()],
            'by_day' => $carcasses->groupBy(fn (Carcass $c) => $c->record->batch->number)->map(fn (Collection $set, string $day) => ['day' => $day] + $summary($set))->values(),
            'low' => $carcasses->filter(fn (Carcass $c) => bccomp((string) $c->dressing_percent, $alert, 2) < 0)->values(),
        ];
    }
}
