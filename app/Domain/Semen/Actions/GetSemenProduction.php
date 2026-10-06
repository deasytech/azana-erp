<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Semen\Models\SemenBoar;
use App\Enums\SemenBoarStatus;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Collections, QC results and doses made per boar over a period, against the weekly targets (a boar's own, else the farm's). */
class GetSemenProduction
{
    public function __construct(private readonly ResolveSettings $settings) {}

    /** @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, int|string|null>} */
    public function __invoke(CarbonInterface $from, CarbonInterface $to): array
    {
        $weeks = bcdiv((string) max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1), '7', 6);
        $farmTarget = (int) $this->settings->get('semen.target_doses_per_week');
        $batches = SemenBatch::with('latestQc')->whereBetween('collected_on', [$from->copy()->startOfDay(), $to->copy()->startOfDay()])->get()->groupBy('animal_id');

        $rows = SemenBoar::with('animal')->get()->map(function (SemenBoar $boar) use ($batches, $weeks, $farmTarget) {
            $own = $batches->get($boar->animal_id, collect());
            $doses = (int) $own->sum('doses_produced');
            $target = $boar->status === SemenBoarStatus::Active ? Ratio::toWhole(bcmul((string) ($boar->target_doses_per_week ?? $farmTarget), $weeks, 6)) : 0;

            return [
                'boar' => $boar->animal->animal_number,
                'animal_id' => $boar->animal_id,
                'status' => $boar->status->label(),
                'collections' => $own->count(),
                // What the laboratory decided, not where the batch is now: a passed batch later expired or destroyed still passed.
                'passed' => $own->filter(fn (SemenBatch $b) => $b->latestQc?->passed === true)->count(),
                'failed' => $own->filter(fn (SemenBatch $b) => $b->latestQc?->passed === false)->count(),
                'doses' => $doses,
                'target_doses' => $target,
                'attainment_percent' => $target > 0 ? Ratio::percent($doses, $target, 1) : null,
            ];
        })->filter(fn (array $row) => $row['collections'] > 0 || $row['target_doses'] > 0)->sortBy('boar')->values();

        $tested = $rows->sum('passed') + $rows->sum('failed');

        return ['rows' => $rows, 'totals' => [
            'collections' => $rows->sum('collections'),
            'doses' => $rows->sum('doses'),
            'target_doses' => $rows->sum('target_doses'),
            'pass_rate_percent' => $tested > 0 ? Ratio::percent($rows->sum('passed'), $tested, 1) : null,
        ]];
    }
}
