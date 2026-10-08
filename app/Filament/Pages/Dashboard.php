<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\Actions\GetKpis;
use App\Domain\Reporting\Actions\GetOwnerSnapshot;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Support\DashboardFocus;
use App\Filament\Widgets\AnimalsByStatusChartWidget;
use App\Filament\Widgets\BornAliveChartWidget;
use App\Filament\Widgets\HerdByCategoryChartWidget;
use App\Filament\Widgets\SalesTrendChartWidget;
use App\Filament\Widgets\TargetAttainmentChartWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Cache;

/** The home screen: the daily snapshot for whoever signs in, limited to what they may see, with the way into everything else. */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.owner-home';

    protected static ?string $title = 'Today on the farm';

    protected static ?string $navigationLabel = 'Home';

    /** The trends and splits behind the snapshot, then this month's indicators against target. Each chart shows only to users who may see its data. */
    protected function getFooterWidgets(): array
    {
        return [SalesTrendChartWidget::class, BornAliveChartWidget::class, AnimalsByStatusChartWidget::class, HerdByCategoryChartWidget::class, TargetAttainmentChartWidget::class];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }

    /** @return array<string, mixed> */
    public function getSnapshotProperty(): array
    {
        return app(GetOwnerSnapshot::class)(auth()->user());
    }

    /**
     * The part of the dashboard that matches the user's job: month-to-date figures for the indicators that matter to it, and shortcuts.
     * Figures the user may not view are left out; null when their roles have no focus. The figures may be up to a minute behind.
     *
     * @return array{heading: string, description: string, kpis: list<array<string, mixed>>, links: list<array{label: string, url: string}>}|null
     */
    public function focus(): ?array
    {
        $user = auth()->user();
        $focus = DashboardFocus::for($user);

        if (! $focus) {
            return null;
        }

        // Working out every indicator the user may view reads most modules, so keep the result for a minute per user and month. The key
        // carries a fingerprint of their permissions, so a role change takes effect at once rather than after the minute.
        $permissions = hash('sha256', $user->getAllPermissions()->pluck('name')->sort()->implode(','));
        $values = Cache::remember(
            "dashboard-focus:{$user->getKey()}:{$permissions}:".now()->format('Y-m'),
            60,
            fn (): array => (app(GetKpis::class))(now()->startOfMonth(), now(), $user),
        );
        $focus['kpis'] = array_values(array_intersect_key($values, array_flip($focus['kpis'])));

        return $focus;
    }

    public function greeting(): string
    {
        return match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * The attention counts from the snapshot, each with the page where it is dealt with; a count the user may not see is left out.
     *
     * @return list<array{label: string, count: int, tone: string, hint: string, url: string}>
     */
    public function attention(): array
    {
        $a = $this->snapshot['attention'];
        $rows = [
            ['critical_alerts', 'Critical alerts', 'danger', 'Review and resolve', Alerts::getUrl()],
            ['overdue_tasks', 'Overdue tasks', 'warning', 'Past their due date', TaskResource::getUrl('index')],
            ['my_open_tasks', 'My open tasks', 'warning', 'Assigned to you', TaskResource::getUrl('index')],
            ['approvals', 'Waiting for approval', 'warning', 'Needs a decision', ApprovalInbox::getUrl()],
        ];

        return array_values(array_map(
            fn (array $r): array => ['label' => $r[1], 'count' => (int) $a[$r[0]], 'tone' => $r[2], 'hint' => $r[3], 'url' => $r[4]],
            array_filter($rows, fn (array $r): bool => $a[$r[0]] !== null),
        ));
    }

    /** @return list<array{label: string, url: string}> */
    public function links(): array
    {
        $user = auth()->user();

        return array_values(array_filter([
            ['label' => 'Trace a product', 'url' => TraceExplorer::getUrl(), 'show' => TraceExplorer::canAccess()],
            ['label' => 'Alerts', 'url' => Alerts::getUrl(), 'show' => Alerts::canAccess()],
            ['label' => 'Approvals', 'url' => ApprovalInbox::getUrl(), 'show' => ApprovalInbox::canAccess()],
            ['label' => 'Dashboards', 'url' => ManagementDashboard::getUrl(), 'show' => ManagementDashboard::canAccess()],
            ['label' => 'Monthly report', 'url' => MonthlyManagementReport::getUrl(), 'show' => MonthlyManagementReport::canAccess()],
            ['label' => 'My tasks', 'url' => TaskResource::getUrl('index'), 'show' => $user->can('tasks.view')],
        ], fn ($l) => $l['show']));
    }
}
