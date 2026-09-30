<?php

namespace App\Domain\Production\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Production\Models\BatchWeighIn;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\BatchEventType;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Production performance of one batch.
 *
 * Growth and feed efficiency are measured between two valid (non-voided) weigh-ins, by default the first and the
 * latest, or two chosen weigh-in dates:
 *  - ADG (kg/day)  = (end average weight - start average weight) / days between the weigh-ins
 *  - gain (kg)     = (end average - start average) x pigs alive on the end weigh-in date
 *  - FCR           = feed eaten after the start weigh-in up to and including the end weigh-in / gain
 * Feed eaten by pigs that later died stays in FCR. Cost per pig divides all costs (entry, feed, other) by the
 * pigs that did not die; cost per kg gained divides feed and other costs by the gain.
 */
class GetBatchPerformance
{
    public function __construct(private readonly ResolveSettings $settings) {}

    /** @return array<string, int|string|null> */
    public function __invoke(ProductionBatch $batch, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $weighIns = $batch->weighIns()->whereNull('voided_at')->orderBy('weighed_on')->get();
        [$start, $end] = $this->period($weighIns, $from, $to);
        $counts = $this->counts($batch);
        $costs = $this->costs($batch);
        $growth = $start && $end ? $this->growth($batch, $start, $end) : [];
        $latest = $weighIns->last();
        $surviving = $counts['placed'] - $counts['deaths'];
        $spend = $costs['feed_cost_minor'] + $costs['other_cost_minor'];
        $overallGain = $weighIns->count() > 1 ? $this->growth($batch, $weighIns->first(), $latest)['gain_kg'] : null;

        return [
            'heads' => $counts['heads'],
            'placed' => $counts['placed'],
            'mortality' => $counts['deaths'],
            'mortality_percent' => Ratio::percent($counts['deaths'], $counts['placed']),
            'latest_weight_kg' => $latest?->average_weight_kg,
            'latest_weigh_in' => $latest?->weighed_on->toDateString(),
            'days_on_feed' => (int) $batch->started_on->diffInDays($latest?->weighed_on ?? $batch->closed_on ?? now()),
            'feed_kg' => $costs['feed_kg'],
            'feed_cost_minor' => $costs['feed_cost_minor'],
            'other_cost_minor' => $costs['other_cost_minor'],
            'entry_cost_minor' => $costs['entry_cost_minor'],
            'total_cost_minor' => $costs['entry_cost_minor'] + $spend,
            'cost_per_pig_minor' => $surviving > 0 ? Ratio::toWhole((string) Ratio::average($costs['entry_cost_minor'] + $spend, $surviving, 4)) : null,
            'cost_per_kg_gain_minor' => $overallGain && bccomp($overallGain, '0', 2) > 0 ? Ratio::toWhole((string) Ratio::average($spend, $overallGain, 4)) : null,
        ] + $growth + $this->market($batch, $weighIns);
    }

    /** @return array{0: ?BatchWeighIn, 1: ?BatchWeighIn} */
    private function period($weighIns, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        if ($weighIns->count() < 2) {
            return [null, null];
        }

        $pick = fn (?CarbonInterface $date, BatchWeighIn $default) => $date
            ? ($weighIns->first(fn ($w) => $w->weighed_on->isSameDay($date)) ?? throw new DomainException('There is no valid weigh-in on '.$date->format('d M Y').'.', 'no_weigh_in'))
            : $default;

        [$start, $end] = [$pick($from, $weighIns->first()), $pick($to, $weighIns->last())];

        if ($end->weighed_on->lte($start->weighed_on)) {
            throw new DomainException('The period must end after it starts.', 'period_order');
        }

        return [$start, $end];
    }

    /** @return array<string, int|string|null> */
    private function growth(ProductionBatch $batch, BatchWeighIn $start, BatchWeighIn $end): array
    {
        $days = (int) $start->weighed_on->diffInDays($end->weighed_on);
        $gainPerHead = bcsub((string) $end->average_weight_kg, (string) $start->average_weight_kg, 2);
        $headsEnd = (int) $batch->events()->whereDate('occurred_on', '<=', $end->weighed_on->toDateString())->sum('delta');
        $gain = bcmul($gainPerHead, (string) $headsEnd, 2);
        $feed = (string) ($batch->feedRecords()->whereNull('voided_at')
            ->whereDate('consumed_on', '>', $start->weighed_on->toDateString())->whereDate('consumed_on', '<=', $end->weighed_on->toDateString())->sum('quantity_kg') ?: '0');

        return [
            'period_start' => $start->weighed_on->toDateString(),
            'period_end' => $end->weighed_on->toDateString(),
            'period_days' => $days,
            'adg_kg' => Ratio::average($gainPerHead, $days, 3),
            'gain_kg' => $gain,
            'period_feed_kg' => bcadd($feed, '0', 2),
            'fcr' => bccomp($gain, '0', 2) > 0 ? Ratio::average($feed, $gain, 2) : null,
        ];
    }

    /** @return array{heads: int, placed: int, deaths: int} */
    private function counts(ProductionBatch $batch): array
    {
        $events = $batch->events()->get(['type', 'delta']);

        return [
            'heads' => (int) $events->sum('delta'),
            'placed' => (int) $events->filter(fn ($e) => $e->type->adds())->sum('delta'),
            'deaths' => (int) abs($events->where('type', BatchEventType::Mortality)->sum('delta')),
        ];
    }

    /** @return array{feed_kg: string, feed_cost_minor: int, other_cost_minor: int, entry_cost_minor: int} */
    private function costs(ProductionBatch $batch): array
    {
        $feed = $batch->feedRecords()->whereNull('voided_at');

        return [
            'feed_kg' => bcadd((string) ($feed->sum('quantity_kg') ?: '0'), '0', 2),
            'feed_cost_minor' => (int) $feed->sum('cost_minor'),
            'other_cost_minor' => (int) $batch->costs()->whereNull('voided_at')->sum('amount_minor'),
            'entry_cost_minor' => (int) $batch->events()->get()->filter(fn ($e) => $e->type->adds() && $e->unit_cost_minor)->sum(fn ($e) => $e->delta * $e->unit_cost_minor),
        ];
    }

    /**
     * When an active batch should reach market weight, at the ADG measured between its first and latest valid
     * weigh-ins, counting from the latest weigh-in date.
     *
     * @param  Collection<int, BatchWeighIn>  $weighIns
     * @return array<string, string|int|null>
     */
    private function market(ProductionBatch $batch, $weighIns): array
    {
        $target = bcadd((string) ($batch->target_weight_kg ?? $this->settings->get('production.target_market_weight_kg')), '0', 2);
        $result = ['target_weight_kg' => $target, 'expected_market_on' => null, 'days_to_market' => null];

        if ($weighIns->count() < 2 || ! $batch->isActive()) {
            return $result;
        }

        [$first, $last] = [$weighIns->first(), $weighIns->last()];
        $days = (int) $first->weighed_on->diffInDays($last->weighed_on);
        $missing = bcsub($target, (string) $last->average_weight_kg, 4);

        if (bccomp($missing, '0', 4) <= 0) {
            return ['expected_market_on' => $last->weighed_on->toDateString(), 'days_to_market' => 0] + $result;
        }

        $gained = bcsub((string) $last->average_weight_kg, (string) $first->average_weight_kg, 4);

        if ($days < 1 || bccomp($gained, '0', 4) <= 0) {
            return $result; // not growing: no honest prediction
        }

        // days = missing / (gained / days) rounded up, done on whole numbers so nothing is truncated early.
        $numerator = bcmul($missing, (string) $days, 4);
        $needed = (int) bcdiv($numerator, $gained, 0);
        $needed += bccomp(bcmul((string) $needed, $gained, 4), $numerator, 4) < 0 ? 1 : 0;

        return ['expected_market_on' => $last->weighed_on->copy()->addDays($needed)->toDateString(), 'days_to_market' => $needed] + $result;
    }

    /** Predicted average weight on a date, continuing at the batch's ADG from its latest weigh-in. */
    public function projectWeight(ProductionBatch $batch, CarbonInterface $on): ?string
    {
        $weighIns = $batch->weighIns()->whereNull('voided_at')->orderBy('weighed_on')->get();

        if ($weighIns->count() < 2) {
            return null;
        }

        [$first, $last] = [$weighIns->first(), $weighIns->last()];
        $span = (int) $first->weighed_on->diffInDays($last->weighed_on);
        $ahead = (int) $last->weighed_on->copy()->startOfDay()->diffInDays($on->copy()->startOfDay(), false);

        if ($span < 1 || $ahead < 0) {
            return null;
        }

        $adg = bcdiv(bcsub((string) $last->average_weight_kg, (string) $first->average_weight_kg, 4), (string) $span, 4);

        return bcadd((string) $last->average_weight_kg, bcmul($adg, (string) $ahead, 4), 2);
    }
}
