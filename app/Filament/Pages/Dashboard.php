<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\Actions\GetOwnerSnapshot;
use App\Filament\Resources\Tasks\TaskResource;
use Filament\Pages\Dashboard as BaseDashboard;

/** The home screen: the daily snapshot for whoever signs in, limited to what they may see, with the way into everything else. */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.owner-home';

    protected static ?string $title = 'Today on the farm';

    protected static ?string $navigationLabel = 'Home';

    /** @return array<string, mixed> */
    public function getSnapshotProperty(): array
    {
        return app(GetOwnerSnapshot::class)(auth()->user());
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
