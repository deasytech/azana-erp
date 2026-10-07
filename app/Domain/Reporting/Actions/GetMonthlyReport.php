<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Finance\Actions\GetBudgetVsActual;
use App\Domain\Finance\Actions\GetCashFlow;
use App\Domain\Finance\Actions\GetProfitability;
use App\Domain\Finance\Actions\GetReceivablesPayables;
use App\Domain\Finance\Models\Budget;
use App\Domain\Tasks\Actions\GetAlerts;
use App\Domain\Tasks\Models\Task;
use App\Enums\BudgetStatus;
use App\Enums\TaskStatus;
use App\Models\User;
use Carbon\Carbon;

/**
 * The monthly management report: the month's indicators against target, profit by business unit, cash flow, what is owed and
 * owing, the plan against actual, the month's tasks, and the alerts standing now. Only what the reader may view is included.
 */
class GetMonthlyReport
{
    public function __construct(
        private readonly GetTargetVsActual $targets, private readonly GetProfitability $profitability, private readonly GetCashFlow $cashFlow,
        private readonly GetReceivablesPayables $ageing, private readonly GetBudgetVsActual $budgetVsActual, private readonly GetAlerts $alerts,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(int $year, int $month, User $for): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();
        $finance = $for->can('finance.view');
        $budget = $finance ? Budget::where('fiscal_year', $year)->where('status', BudgetStatus::Approved)->orderByDesc('id')->first() : null;
        $tasks = $for->can('tasks.view') ? $this->tasks($from, $to) : null;

        return [
            'year' => $year, 'month' => $month, 'label' => $from->format('F Y'), 'from' => $from, 'to' => $to, 'generated_at' => now(), 'generated_by' => $for->name,
            'kpis' => ($this->targets)($year, $month, $for, now()),
            'profitability' => $finance ? ($this->profitability)($from, $to) : null,
            'cash_flow' => $finance ? ($this->cashFlow)($from, $to) : null,
            'ageing' => $for->can('sales.view') || $finance ? ($this->ageing)($to) : null,
            'budget' => $budget ? ['name' => $budget->name] + ($this->budgetVsActual)($budget, $month) : null,
            'tasks' => $tasks,
            'alerts' => ($this->alerts)($for)->countBy('severity')->all() + ['danger' => 0, 'warning' => 0, 'info' => 0],
        ];
    }

    /** @return array{created: int, completed: int, overdue: int} */
    private function tasks(Carbon $from, Carbon $to): array
    {
        return [
            'created' => Task::whereBetween('created_at', [$from, $to])->count(),
            'completed' => Task::where('status', TaskStatus::Done)->whereBetween('completed_at', [$from, $to])->count(),
            'overdue' => Task::whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])->whereDate('due_on', '<', min($to, now())->toDateString())->count(),
        ];
    }
}
