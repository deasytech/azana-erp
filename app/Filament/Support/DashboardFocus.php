<?php

namespace App\Filament\Support;

use App\Filament\Pages\BreedingCalendar;
use App\Filament\Pages\CashFlowReport;
use App\Filament\Pages\ManagementDashboard;
use App\Filament\Pages\MonthlyManagementReport;
use App\Filament\Pages\MortalityAnalysis;
use App\Filament\Pages\ProfitabilityReport;
use App\Filament\Pages\ReceivablesPayables;
use App\Filament\Pages\StockAlerts;
use App\Filament\Pages\StockOverview;
use App\Filament\Pages\SupplierBalances;
use App\Filament\Pages\VaccinationsDue;
use App\Models\Role;
use App\Models\User;

/**
 * What the Home dashboard puts first for each kind of job: a heading, the indicators that matter to it and the pages it opens most.
 * Presentation only: the figures still come from GetKpis and are limited to the modules the user may view, and a shortcut is shown only
 * when the user can open its page. A user whose roles match no profile (for example a farm worker) sees the standard dashboard alone.
 */
class DashboardFocus
{
    /** Checked in order; the first profile one of the user's roles belongs to wins. */
    private const PROFILES = [
        'management' => [
            'roles' => [Role::OWNER, 'General Manager'],
            'heading' => 'Management focus',
            'description' => 'Herd, production, revenue, costs and profit for the month so far.',
            'kpis' => ['herd.active_animals', 'production.growing_pigs', 'sales.invoiced_minor', 'finance.direct_cost_minor', 'finance.net_margin_minor', 'finance.net_margin_percent'],
            'links' => [[ManagementDashboard::class, 'Dashboards'], [ProfitabilityReport::class, 'Profitability'], [MonthlyManagementReport::class, 'Monthly report']],
        ],
        'supervisor' => [
            'roles' => ['Farm Manager', 'Breeding Manager', 'Veterinarian'],
            'heading' => 'Farm supervision focus',
            'description' => 'Animals, breeding results, mortality and feed for the month so far.',
            'kpis' => ['herd.active_animals', 'herd.weaning_percent', 'herd.pre_weaning_mortality_percent', 'production.active_batches', 'production.mortality_percent', 'feed.consumed_kg'],
            'links' => [[VaccinationsDue::class, 'Vaccinations due'], [BreedingCalendar::class, 'Breeding calendar'], [MortalityAnalysis::class, 'Mortality']],
        ],
        'inventory' => [
            'roles' => ['Store Officer'],
            'heading' => 'Stores focus',
            'description' => 'Stock on hand and what is running low or owed to suppliers.',
            'kpis' => ['feed.stock_kg', 'finance.payables_minor', 'feed.produced_kg'],
            'links' => [[StockAlerts::class, 'Stock alerts'], [StockOverview::class, 'Stock on hand'], [SupplierBalances::class, 'Supplier balances']],
        ],
        'accountant' => [
            'roles' => ['Accountant'],
            'heading' => 'Finance focus',
            'description' => 'Revenue, costs, cash and what is owed, for the month so far.',
            'kpis' => ['finance.revenue_minor', 'finance.direct_cost_minor', 'finance.net_margin_minor', 'finance.cash_minor', 'sales.receivables_minor', 'finance.payables_minor'],
            'links' => [[CashFlowReport::class, 'Cash flow'], [ReceivablesPayables::class, 'Receivables & payables'], [ProfitabilityReport::class, 'Profitability']],
        ],
    ];

    /**
     * @return array{key: string, heading: string, description: string, kpis: list<string>, links: list<array{label: string, url: string}>}|null
     */
    public static function for(User $user): ?array
    {
        foreach (self::PROFILES as $key => $profile) {
            if ($user->hasAnyRole($profile['roles'])) {
                return [
                    'key' => $key,
                    'heading' => $profile['heading'],
                    'description' => $profile['description'],
                    'kpis' => $profile['kpis'],
                    'links' => array_values(array_map(
                        fn (array $l): array => ['label' => $l[1], 'url' => $l[0]::getUrl()],
                        array_filter($profile['links'], fn (array $l): bool => $l[0]::canAccess()),
                    )),
                ];
            }
        }

        return null;
    }
}
