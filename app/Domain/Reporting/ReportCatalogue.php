<?php

namespace App\Domain\Reporting;

use App\Models\User;

/**
 * The reports the owner can run from the Reports group: each is a title, a plain description and the dashboards (KpiRegistry areas) whose
 * indicators it covers. A user sees a report only if they may view at least one indicator in it.
 */
final class ReportCatalogue
{
    /** @return array<string, array{title: string, description: string, icon: string, areas: list<string>}> */
    public static function all(): array
    {
        return [
            'business' => ['title' => 'Business summary', 'description' => 'Every figure of the farm in one report: herd, feed, semen, meat, sales and money.', 'icon' => 'heroicon-o-building-office-2', 'areas' => array_keys(KpiRegistry::AREAS)],
            'semen' => ['title' => 'Semen produced', 'description' => 'Collections and doses made, quality-check pass rate and doses in stock, with doses per boar.', 'icon' => 'heroicon-o-beaker', 'areas' => ['semen']],
            'feed' => ['title' => 'Feed produced', 'description' => 'Feed made at the mill, its cost per kg, what the pigs ate and finished feed in stock.', 'icon' => 'heroicon-o-cube', 'areas' => ['feed']],
            'herd' => ['title' => 'Herd and breeding', 'description' => 'Animals by category, piglets born alive, weaning rate and pre-weaning losses.', 'icon' => 'heroicon-o-heart', 'areas' => ['herd']],
            'production' => ['title' => 'Grower and finisher production', 'description' => 'Pigs growing, active batches, batch mortality and cost per pig.', 'icon' => 'heroicon-o-chart-bar', 'areas' => ['production']],
            'slaughter' => ['title' => 'Slaughter and meat', 'description' => 'Pigs slaughtered, dressing percentage and meat in the cold rooms.', 'icon' => 'heroicon-o-scissors', 'areas' => ['slaughter']],
            'sales' => ['title' => 'Sales and customers', 'description' => 'Sales invoiced, money received, what customers owe and what is overdue.', 'icon' => 'heroicon-o-shopping-cart', 'areas' => ['sales']],
            'finance' => ['title' => 'Money: revenue, costs and cash', 'description' => 'Revenue, direct costs, margins, cash and bank, and what is owed to suppliers.', 'icon' => 'heroicon-o-banknotes', 'areas' => ['finance']],
        ];
    }

    /** @return array<string, array{title: string, description: string, icon: string, areas: list<string>}> the reports this user may run */
    public static function for(User $user): array
    {
        return array_filter(self::all(), fn (array $report) => self::visibleAreas($report, $user) !== []);
    }

    /** @return list<string> the report's areas that the user may view (any indicator of the area) */
    public static function visibleAreas(array $report, User $user): array
    {
        $modules = collect(KpiRegistry::all())->groupBy('area')->map(fn ($kpis) => $kpis->pluck('module')->unique());

        return array_values(array_filter($report['areas'], fn (string $area) => $user->is_active && $modules->get($area, collect())->contains(fn (string $m) => $user->can("{$m}.view"))));
    }
}
