<?php

namespace App\Domain\Health\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Health\Models\MortalityRecord;
use App\Domain\Litter\Models\LitterLoss;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Deaths grouped by pen, production stage, age band, litter, sow, breed, cause or month.
 * Combines individual mortality records with pre-weaning losses of untracked piglets (counts on a litter),
 * so no death is missed and none is counted twice.
 */
class GetMortalityAnalysis
{
    public const DIMENSIONS = ['pen', 'stage', 'age_band', 'litter', 'sow', 'breed', 'cause', 'month'];

    private const UNKNOWN = 'Unknown';

    public function __construct(private readonly ResolveSettings $settings) {}

    /** @return array{total: int, rows: Collection<int, array{label: string, count: int, percent: ?string}>} */
    public function __invoke(CarbonInterface $from, CarbonInterface $to, string $dimension): array
    {
        in_array($dimension, self::DIMENSIONS, true) || throw new InvalidArgumentException("Unknown dimension [{$dimension}].");

        $deaths = $this->deaths($from, $to);
        $total = $deaths->sum('count');

        $rows = $deaths->groupBy(fn (array $d) => $d[$dimension])
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'count' => $group->sum('count'),
                'percent' => Ratio::percent($group->sum('count'), $total),
            ])
            ->sortByDesc('count')
            ->values();

        return ['total' => $total, 'rows' => $rows];
    }

    /** @return Collection<int, array<string, int|string>> */
    private function deaths(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $limits = $this->bandLimits();
        $range = [$from->toDateString(), $to->toDateString()];

        $tracked = MortalityRecord::with(['pen', 'category', 'breed', 'litter', 'sow', 'cause'])
            ->whereDate('died_on', '>=', $range[0])->whereDate('died_on', '<=', $range[1])->get()
            ->map(fn (MortalityRecord $m) => $this->row(1, $m->died_on, $m->age_days, $limits, [
                'pen' => $m->pen?->code, 'stage' => $m->category->name, 'litter' => $m->litter?->litter_number,
                'sow' => $m->sow?->animal_number, 'breed' => $m->breed?->name, 'cause' => $m->cause->name,
            ]));

        $untracked = LitterLoss::with(['litter.sow.breed'])->whereNull('animal_id')
            ->whereDate('occurred_on', '>=', $range[0])->whereDate('occurred_on', '<=', $range[1])->get()
            ->map(fn (LitterLoss $l) => $this->row($l->count, $l->occurred_on, (int) $l->litter->born_on->diffInDays($l->occurred_on), $limits, [
                'stage' => 'Piglet', 'litter' => $l->litter->litter_number, 'sow' => $l->litter->sow->animal_number,
                'breed' => $l->litter->sow->breed?->name, 'cause' => $l->cause,
            ]));

        return $tracked->concat($untracked)->values();
    }

    /**
     * @param  list<int>  $limits
     * @param  array<string, ?string>  $dimensions
     * @return array<string, int|string>
     */
    private function row(int $count, CarbonInterface $on, ?int $ageDays, array $limits, array $dimensions): array
    {
        return array_map(fn ($v) => $v ?? self::UNKNOWN, $dimensions) + [
            'count' => $count,
            'month' => $on->format('Y-m'),
            'age_band' => $this->band($ageDays, $limits),
        ] + ['pen' => self::UNKNOWN];
    }

    /** @param list<int> $limits */
    private function band(?int $ageDays, array $limits): string
    {
        if ($ageDays === null) {
            return self::UNKNOWN;
        }

        $lower = 0;

        foreach ($limits as $upper) {
            if ($ageDays <= $upper) {
                return "{$lower}-{$upper} days";
            }

            $lower = $upper + 1;
        }

        return "{$lower}+ days";
    }

    /** @return list<int> */
    private function bandLimits(): array
    {
        $limits = collect(explode(',', (string) $this->settings->get('health.mortality_age_band_limits')))
            ->map(fn ($v) => (int) trim($v))->filter(fn ($v) => $v > 0)->unique()->sort()->values()->all();

        return $limits ?: [7, 28, 70, 150];
    }
}
