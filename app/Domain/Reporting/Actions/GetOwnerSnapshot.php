<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\KpiRegistry;
use App\Domain\Tasks\Actions\GetAlerts;
use App\Domain\Tasks\Actions\GetPendingApprovals;
use App\Domain\Tasks\Models\Task;
use App\Enums\TaskStatus;
use App\Models\User;

/**
 * The owner's daily snapshot: today's sales, receipts, doses and slaughter; what the farm holds and owes now; what needs attention (critical
 * alerts, overdue tasks, approvals waiting); and this month's headline indicators against target. Only what the user may view appears.
 */
class GetOwnerSnapshot
{
    /** What is shown for today, as flows over the day; the rest are levels as at now. */
    private const TODAY = ['sales.invoiced_minor', 'sales.receipts_minor', 'semen.doses', 'slaughter.pigs', 'feed.produced_kg'];

    private const NOW = ['herd.active_animals', 'production.growing_pigs', 'finance.cash_minor', 'sales.receivables_minor', 'sales.overdue_minor', 'finance.payables_minor', 'semen.doses_in_stock', 'slaughter.meat_stock_kg', 'feed.stock_kg'];

    public function __construct(private readonly GetKpis $kpis, private readonly GetTargetVsActual $targets, private readonly GetAlerts $alerts, private readonly GetPendingApprovals $approvals) {}

    /** @return array<string, mixed> */
    public function __invoke(User $user): array
    {
        $today = now()->startOfDay();
        $kpis = ($this->kpis)($today, $today, $user);
        $pick = fn (array $keys) => array_values(array_intersect_key($kpis, array_flip($keys)));
        $canTasks = $user->can('tasks.view');
        $openTasks = $canTasks ? Task::whereIn('status', [TaskStatus::Open, TaskStatus::InProgress]) : null;

        return [
            'today' => $pick(self::TODAY),
            'now' => $pick(self::NOW),
            'month' => array_values(array_filter(($this->targets)((int) now()->year, (int) now()->month, $user, now()), fn ($k) => $k['executive'])),
            'attention' => [
                'critical_alerts' => $canTasks ? ($this->alerts)($user)->where('severity', 'danger')->count() : null,
                'overdue_tasks' => $openTasks ? (clone $openTasks)->whereDate('due_on', '<', $today->toDateString())->count() : null,
                'my_open_tasks' => $openTasks ? (clone $openTasks)->where('assigned_to', $user->id)->count() : null,
                'approvals' => ($this->approvals)($user)->reject(fn ($i) => $i['mine'])->count(),
            ],
            'units' => collect(KpiRegistry::all())->map(fn ($k) => $k['unit'])->all(),
        ];
    }
}
