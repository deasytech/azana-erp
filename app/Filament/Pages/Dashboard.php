<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\Actions\GetOwnerSnapshot;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Widgets\TargetAttainmentChartWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/** The home screen: the daily snapshot for whoever signs in, limited to what they may see, with the way into everything else. */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.owner-home';

    protected static ?string $title = 'Today on the farm';

    protected static ?string $navigationLabel = 'Home';

    /** This month's headline indicators drawn against target, below the snapshot. */
    protected function getFooterWidgets(): array
    {
        return [TargetAttainmentChartWidget::class];
    }

    public function getFooterWidgetsColumns(): int
    {
        return 1;
    }

    /** @return array<string, mixed> */
    public function getSnapshotProperty(): array
    {
        return app(GetOwnerSnapshot::class)(auth()->user());
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
