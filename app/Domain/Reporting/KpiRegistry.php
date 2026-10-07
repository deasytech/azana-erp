<?php

namespace App\Domain\Reporting;

use App\Domain\Farm\Models\Farm;
use App\Support\Money;

/**
 * The farm's key performance indicators and what each means, in one place: every dashboard, the target-vs-actual screen and the
 * monthly report read the same list, so a figure is defined once. For each: label, area (a dashboard), module (whose view permission
 * is needed to see it), unit (count, kg, doses, percent, money), which direction is good (higher / lower / none), whether it is on the
 * executive dashboard, and optionally the farm setting that holds its default target until management sets one.
 */
final class KpiRegistry
{
    /** Dashboards, in order, with the module whose view permission opens each one. */
    public const AREAS = [
        'executive' => ['Executive', 'reports'], 'herd' => ['Herd', 'animals'], 'production' => ['Production', 'production'], 'feed' => ['Feed', 'feed-mill'],
        'semen' => ['Semen', 'semen'], 'slaughter' => ['Slaughter & meat', 'slaughter'], 'sales' => ['Sales', 'sales'], 'finance' => ['Finance', 'finance'],
    ];

    /** @return array<string, array{label: string, area: string, module: string, unit: string, better: string, executive: bool, setting: ?string}> */
    public static function all(): array
    {
        $kpi = fn (string $label, string $area, string $module, string $unit, string $better = 'none', bool $executive = false, ?string $setting = null) => compact('label', 'area', 'module', 'unit', 'better', 'executive', 'setting');

        return [
            'herd.active_animals' => $kpi('Active animals', 'herd', 'animals', 'count', 'none', true),
            'herd.sows' => $kpi('Sows', 'herd', 'animals', 'count'),
            'herd.boars' => $kpi('Boars', 'herd', 'animals', 'count'),
            'herd.gilts' => $kpi('Gilts', 'herd', 'animals', 'count'),
            'herd.born_alive' => $kpi('Piglets born alive', 'herd', 'breeding', 'count', 'higher'),
            'herd.weaning_percent' => $kpi('Weaning rate', 'herd', 'breeding', 'percent', 'higher', false, 'production.target_weaning_percent'),
            'herd.pre_weaning_mortality_percent' => $kpi('Pre-weaning mortality', 'herd', 'breeding', 'percent', 'lower', false, 'production.target_preweaning_mortality_percent'),
            'production.growing_pigs' => $kpi('Pigs in growing batches', 'production', 'production', 'count', 'none', true),
            'production.active_batches' => $kpi('Active batches', 'production', 'production', 'count'),
            'production.mortality_percent' => $kpi('Batch mortality', 'production', 'production', 'percent', 'lower', false, 'production.batch_mortality_alert_percent'),
            'production.cost_per_pig_minor' => $kpi('Average cost per pig', 'production', 'production', 'money', 'lower'),
            'feed.produced_kg' => $kpi('Feed produced', 'feed', 'feed-mill', 'kg', 'higher'),
            'feed.cost_per_kg_minor' => $kpi('Feed cost per kg', 'feed', 'feed-mill', 'money', 'lower'),
            'feed.consumed_kg' => $kpi('Feed eaten by batches', 'feed', 'production', 'kg'),
            'feed.stock_kg' => $kpi('Finished feed in stock', 'feed', 'inventory', 'kg'),
            'semen.doses' => $kpi('Doses produced', 'semen', 'semen', 'doses', 'higher', true),
            'semen.attainment_percent' => $kpi('Share of dose target reached', 'semen', 'semen', 'percent', 'higher'),
            'semen.pass_rate_percent' => $kpi('QC pass rate', 'semen', 'semen', 'percent', 'higher'),
            'semen.doses_in_stock' => $kpi('Sellable doses in stock', 'semen', 'semen', 'doses'),
            'slaughter.pigs' => $kpi('Pigs slaughtered', 'slaughter', 'slaughter', 'count', 'none', true),
            'slaughter.dressing_percent' => $kpi('Dressing percentage', 'slaughter', 'slaughter', 'percent', 'higher', false, 'production.target_dressing_percent'),
            'slaughter.meat_stock_kg' => $kpi('Meat in cold rooms', 'slaughter', 'slaughter', 'kg'),
            'sales.invoiced_minor' => $kpi('Sales invoiced', 'sales', 'sales', 'money', 'higher', true),
            'sales.receipts_minor' => $kpi('Money received from customers', 'sales', 'sales', 'money', 'higher'),
            'sales.receivables_minor' => $kpi('Owed by customers', 'sales', 'sales', 'money', 'lower', true),
            'sales.overdue_minor' => $kpi('Overdue from customers', 'sales', 'sales', 'money', 'lower'),
            'finance.revenue_minor' => $kpi('Revenue', 'finance', 'finance', 'money', 'higher'),
            'finance.direct_cost_minor' => $kpi('Direct costs', 'finance', 'finance', 'money', 'lower'),
            'finance.gross_margin_percent' => $kpi('Gross margin', 'finance', 'finance', 'percent', 'higher'),
            'finance.net_margin_minor' => $kpi('Net margin', 'finance', 'finance', 'money', 'higher', true),
            'finance.net_margin_percent' => $kpi('Net margin (%)', 'finance', 'finance', 'percent', 'higher'),
            'finance.cash_minor' => $kpi('Cash and bank', 'finance', 'finance', 'money', 'higher', true),
            'finance.payables_minor' => $kpi('Owed to suppliers', 'finance', 'finance', 'money', 'lower'),
        ];
    }

    /** @return array<string, string> kpi key => label, for a target form */
    public static function options(): array
    {
        return array_map(fn (array $k) => $k['label'].' ('.self::AREAS[$k['area']][0].')', self::all());
    }

    /** A value as people read it: money in the farm currency, percentages with %, weights with kg. */
    public static function format(string $unit, int|string|null $value): string
    {
        if ($value === null) {
            return '-';
        }

        return match ($unit) {
            'money' => Money::ofMinor((int) $value, Farm::defaultCurrency())->format(),
            'percent' => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'%',
            'kg' => number_format((float) $value, 2).' kg',
            default => number_format((float) $value, 0),
        };
    }
}
