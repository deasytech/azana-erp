<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Finance\Actions\GetCashFlow;
use App\Domain\Finance\Actions\GetProfitability;
use App\Domain\Finance\Actions\GetReceivablesPayables;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Litter\Actions\GetLitterKpis;
use App\Domain\Litter\Models\Litter;
use App\Domain\Litter\Models\WeaningRecord;
use App\Domain\Meat\Actions\GetMeatStock;
use App\Domain\Production\Actions\GetProductionSummary;
use App\Domain\Reporting\KpiRegistry;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Semen\Actions\GetSemenProduction;
use App\Domain\Semen\Actions\GetSemenStock;
use App\Domain\Slaughter\Actions\GetSlaughterYield;
use App\Enums\AnimalStatus;
use App\Enums\InventoryCategory;
use App\Models\User;
use App\Support\Ratio;
use Carbon\CarbonInterface;

/**
 * The value of every indicator in KpiRegistry over a period (flows such as sales and doses are totals for the period; levels such as
 * animals, stock and what is owed are as at its end). Every screen takes its figures from here, so they cannot disagree. Pass a user to
 * get only the indicators of modules they may view. A value is a string or integer, or null when it cannot be worked out (no data).
 */
class GetKpis
{
    public function __construct(
        private readonly GetProductionSummary $production, private readonly GetSemenProduction $semen, private readonly GetSemenStock $semenStock,
        private readonly GetSlaughterYield $slaughter, private readonly GetMeatStock $meatStock, private readonly GetProfitability $profitability,
        private readonly GetCashFlow $cashFlow, private readonly GetReceivablesPayables $ageing, private readonly GetStockLevels $stock, private readonly GetLitterKpis $litterKpis,
    ) {}

    /** @return array<string, array<string, mixed>> the registry's entries, each with a 'value' */
    public function __invoke(CarbonInterface $from, CarbonInterface $to, ?User $for = null): array
    {
        $wanted = collect(KpiRegistry::all())->filter(fn ($k) => ! $for || ($for->is_active && $for->can("{$k['module']}.view")));
        $values = [];

        foreach ($wanted->pluck('area')->unique() as $area) {
            $values += $this->{$area}($from->copy()->startOfDay(), $to->copy()->endOfDay());
        }

        return $wanted->map(fn ($k, $key) => $k + ['key' => $key, 'value' => $values[$key] ?? null])->all();
    }

    /** @return array<string, int|string|null> */
    private function herd(CarbonInterface $from, CarbonInterface $to): array
    {
        $active = fn (string $category) => Animal::where('status', AnimalStatus::Active)->whereHas('category', fn ($q) => $q->where('code', $category))->count();
        $born = Litter::whereBetween('born_on', [$from->toDateString(), $to->toDateString()])->with('farrowing')->get();
        $kpis = $born->map(fn (Litter $l) => ($this->litterKpis)($l));
        $weaned = WeaningRecord::whereBetween('weaned_on', [$from->toDateString(), $to->toDateString()])->with('litter')->get();

        return [
            'herd.active_animals' => Animal::where('status', AnimalStatus::Active)->count(),
            'herd.sows' => $active('sow'), 'herd.boars' => $active('boar'), 'herd.gilts' => $active('gilt'),
            'herd.born_alive' => (int) $kpis->sum('born_alive'),
            // Of the piglets born alive in litters born in the period: how many were lost before weaning, and how many were weaned.
            'herd.pre_weaning_mortality_percent' => Ratio::percent((int) $kpis->sum('pre_weaning_losses'), (int) $kpis->sum('born_alive')),
            'herd.weaning_percent' => $weaned->isEmpty() ? null : Ratio::percent((int) $weaned->sum('weaned_count'), (int) $weaned->sum(fn ($w) => ($this->litterKpis)($w->litter)['born_alive'])),
        ];
    }

    /** @return array<string, int|string|null> */
    private function production(): array
    {
        $rows = ($this->production)();
        $placed = (int) $rows->sum(fn ($r) => $r['performance']['placed']);
        $deaths = (int) $rows->sum(fn ($r) => $r['performance']['mortality']);
        $surviving = $placed - $deaths;
        $cost = (int) $rows->sum(fn ($r) => $r['performance']['total_cost_minor']);

        return [
            'production.growing_pigs' => (int) $rows->sum(fn ($r) => $r['performance']['heads']),
            'production.active_batches' => $rows->count(),
            'production.mortality_percent' => Ratio::percent($deaths, $placed),
            'production.cost_per_pig_minor' => $surviving > 0 ? Ratio::toWhole((string) Ratio::average($cost, $surviving, 4)) : null,
        ];
    }

    /** @return array<string, int|string|null> */
    private function feed(CarbonInterface $from, CarbonInterface $to): array
    {
        $made = FeedProductionBatch::whereNull('reversed_at')->whereDate('produced_on', '>=', $from->toDateString())->whereDate('produced_on', '<=', $to->toDateString());
        $kg = (string) ($made->sum('output_kg') ?: '0');
        $eaten = FeedConsumptionRecord::whereNull('voided_at')->whereDate('consumed_on', '>=', $from->toDateString())->whereDate('consumed_on', '<=', $to->toDateString())->sum('quantity_kg');
        $finished = $this->stock->__invoke()->filter(fn ($layer) => $layer->item->category === InventoryCategory::FinishedFeed)->sum(fn ($layer) => (float) $layer->on_hand);

        return [
            'feed.produced_kg' => bcadd($kg, '0', 2),
            'feed.cost_per_kg_minor' => bccomp($kg, '0', 2) > 0 ? Ratio::toWhole((string) Ratio::average((int) $made->sum('total_cost_minor'), $kg, 4)) : null,
            'feed.consumed_kg' => bcadd((string) ($eaten ?: '0'), '0', 2),
            'feed.stock_kg' => number_format($finished, 2, '.', ''),
        ];
    }

    /** @return array<string, int|string|null> */
    private function semen(CarbonInterface $from, CarbonInterface $to): array
    {
        $t = ($this->semen)($from, $to)['totals'];

        return [
            'semen.doses' => (int) $t['doses'],
            'semen.attainment_percent' => $t['target_doses'] > 0 ? Ratio::percent((int) $t['doses'], (int) $t['target_doses'], 1) : null,
            'semen.pass_rate_percent' => $t['pass_rate_percent'],
            'semen.doses_in_stock' => (int) ($this->semenStock)()->where('sellable', true)->sum(fn ($r) => (float) $r['doses']),
        ];
    }

    /** @return array<string, int|string|null> */
    private function slaughter(CarbonInterface $from, CarbonInterface $to): array
    {
        $t = ($this->slaughter)($from, $to)['totals'];

        return [
            'slaughter.pigs' => (int) $t['heads'],
            'slaughter.dressing_percent' => $t['dressing_percent'],
            'slaughter.meat_stock_kg' => bcadd((string) ($this->meatStock)()->where('expired', false)->reduce(fn ($s, $r) => bcadd($s, (string) $r['kg'], 3), '0'), '0', 2),
        ];
    }

    /** @return array<string, int|string|null> */
    private function sales(CarbonInterface $from, CarbonInterface $to): array
    {
        $receivables = ($this->ageing)($to)['receivables'];

        return [
            'sales.invoiced_minor' => (int) Invoice::whereDate('issued_on', '>=', $from->toDateString())->whereDate('issued_on', '<=', $to->toDateString())->sum('total_minor'),
            'sales.receipts_minor' => (int) Payment::whereNull('voided_at')->whereDate('received_on', '>=', $from->toDateString())->whereDate('received_on', '<=', $to->toDateString())->sum('amount_minor'),
            'sales.receivables_minor' => $receivables['total_minor'],
            'sales.overdue_minor' => $receivables['total_minor'] - $receivables['buckets']['current'],
        ];
    }

    /** @return array<string, int|string|null> */
    private function finance(CarbonInterface $from, CarbonInterface $to): array
    {
        $p = ($this->profitability)($from, $to)['totals'];

        return [
            'finance.revenue_minor' => $p['revenue_minor'], 'finance.direct_cost_minor' => $p['direct_cost_minor'],
            'finance.gross_margin_percent' => $p['gross_margin_percent'], 'finance.net_margin_minor' => $p['net_margin_minor'], 'finance.net_margin_percent' => $p['net_margin_percent'],
            'finance.cash_minor' => ($this->cashFlow)($from, $to)['closing_minor'],
            'finance.payables_minor' => ($this->ageing)($to)['payables']['total_minor'],
        ];
    }
}
