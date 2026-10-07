<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionCost;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\AccountType;
use App\Enums\MeatProductionStatus;
use App\Support\Ratio;
use Carbon\CarbonInterface;

/**
 * Margin by business unit (cost centre) over a period.
 *  - Revenue: sales posted to the ledger, by the cost centre that earned them.
 *  - Direct cost: what each unit spent, taken where it is consumed so nothing is counted twice: grower/finisher/piglet batches
 *    (feed eaten and other batch costs), the feed mill's and meat processing's conversion costs, semen at the cost per dose.
 *  - Overheads: expense accounts in the ledger charged to a cost centre (supplier purchases are excluded, because what they
 *    bought reaches the units as the feed, semen and meat costs above). Unassigned overheads are shown apart.
 * Gross margin = revenue - direct cost. Net margin = gross margin - overheads.
 */
class GetProfitability
{
    public function __construct(private readonly Ledger $ledger, private readonly ResolveSettings $settings) {}

    /** @return array{rows: list<array<string, mixed>>, totals: array<string, int|string|null>} */
    public function __invoke(CarbonInterface $from, CarbonInterface $to): array
    {
        $revenue = $this->byCentre($this->ledger->lines($from, $to)->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')->where('accounts.type', AccountType::Revenue->value), true);
        $overhead = $this->byCentre($this->ledger->lines($from, $to)->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')->where('accounts.type', AccountType::Expense->value)
            ->where(fn ($q) => $q->whereNull('accounts.system_key')->orWhere('accounts.system_key', '!=', 'purchases')), false);
        $direct = $this->direct($from, $to);

        $rows = CostCentre::orderBy('code')->get()->map(function (CostCentre $c) use ($revenue, $direct, $overhead) {
            [$r, $d, $o] = [$revenue[$c->id] ?? 0, $direct[$c->code] ?? 0, $overhead[$c->id] ?? 0];

            return ['centre' => $c, 'revenue_minor' => $r, 'direct_cost_minor' => $d, 'gross_margin_minor' => $r - $d, 'overhead_minor' => $o, 'net_margin_minor' => $r - $d - $o];
        })->filter(fn ($r) => $r['revenue_minor'] || $r['direct_cost_minor'] || $r['overhead_minor'])->values()->all();

        $unassignedRevenue = $revenue[0] ?? 0;
        $unassignedOverhead = $overhead[0] ?? 0;
        $totalRevenue = array_sum(array_column($rows, 'revenue_minor')) + $unassignedRevenue;
        $totalDirect = array_sum(array_column($rows, 'direct_cost_minor'));
        $totalOverhead = array_sum(array_column($rows, 'overhead_minor')) + $unassignedOverhead;

        return ['rows' => $rows, 'totals' => [
            'revenue_minor' => $totalRevenue, 'direct_cost_minor' => $totalDirect, 'gross_margin_minor' => $totalRevenue - $totalDirect,
            'gross_margin_percent' => Ratio::percent($totalRevenue - $totalDirect, $totalRevenue), 'overhead_minor' => $totalOverhead,
            'net_margin_minor' => $totalRevenue - $totalDirect - $totalOverhead, 'net_margin_percent' => Ratio::percent($totalRevenue - $totalDirect - $totalOverhead, $totalRevenue),
            'unassigned_revenue_minor' => $unassignedRevenue, 'unassigned_overhead_minor' => $unassignedOverhead,
        ]];
    }

    /** @return array<int, int> amount by cost centre id (0 = no cost centre) */
    private function byCentre($query, bool $creditNormal): array
    {
        $amount = $creditNormal ? 'journal_lines.credit_minor - journal_lines.debit_minor' : 'journal_lines.debit_minor - journal_lines.credit_minor';

        return $query->reorder()->selectRaw("coalesce(journal_lines.cost_centre_id, 0) as centre, sum({$amount}) as amount")
            ->groupBy('centre')->pluck('amount', 'centre')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, int> direct cost by cost centre code */
    private function direct(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $cost = [];
        $add = function (string $code, int $minor) use (&$cost) {
            $cost[$code] = ($cost[$code] ?? 0) + $minor;
        };

        $stages = ProductionBatch::with('stage')->get()->mapWithKeys(fn (ProductionBatch $b) => [$b->id => $this->stageCentre((string) $b->stage?->code)]);
        FeedConsumptionRecord::whereNull('voided_at')->whereDate('consumed_on', '>=', $range[0])->whereDate('consumed_on', '<=', $range[1])->get()->each(fn ($r) => $add($stages[$r->production_batch_id] ?? 'GRW', (int) $r->cost_minor));
        ProductionCost::whereNull('voided_at')->whereDate('incurred_on', '>=', $range[0])->whereDate('incurred_on', '<=', $range[1])->get()->each(fn ($c) => $add($stages[$c->production_batch_id] ?? 'GRW', (int) $c->amount_minor));

        $add('FDM', (int) FeedProductionBatch::whereNull('reversed_at')->whereDate('produced_on', '>=', $range[0])->whereDate('produced_on', '<=', $range[1])->sum('other_cost_minor'));
        $add('MEA', (int) MeatProductionBatch::where('status', MeatProductionStatus::Produced)->whereDate('produced_on', '>=', $range[0])->whereDate('produced_on', '<=', $range[1])->sum('other_cost_minor'));

        $perDose = (int) $this->settings->get('semen.cost_per_dose_minor');
        $add('SEM', (int) SemenBatch::whereNotNull('released_at')->whereBetween('released_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->sum('doses_produced') * $perDose);

        return array_filter($cost);
    }

    /** Which cost centre a production batch's costs belong to, from its stage. */
    private function stageCentre(string $stage): string
    {
        return match (true) {
            str_contains($stage, 'finish') => 'FIN',
            str_contains($stage, 'grow') => 'GRW',
            default => 'PIG',
        };
    }
}
