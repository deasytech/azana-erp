<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Breeding\Actions\GetBreedingCalendar;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Finance\Actions\GetReceivablesPayables;
use App\Domain\Finance\Actions\GetTrialBalance;
use App\Domain\Health\Actions\GetHealthAlerts;
use App\Domain\Inventory\Actions\GetExpiryAlerts;
use App\Domain\Inventory\Actions\GetReorderAlerts;
use App\Domain\Production\Actions\GetProductionSummary;
use App\Domain\Sales\Actions\GetOutstandingBalances;
use App\Domain\Tasks\Models\Task;
use App\Enums\TaskPriority;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Every alert the system can raise from its own data, worst first: health, stock, breeding, production, sales and credit, finance,
 * and overdue tasks. Each carries a stable key (so the same alert is not announced twice), a severity (danger / warning / info)
 * and the module whose permission is needed to see it. Pass a user to get only the alerts for areas they may view.
 */
class GetAlerts
{
    private const ORDER = ['danger' => 0, 'warning' => 1, 'info' => 2];

    public function __construct(
        private readonly GetHealthAlerts $health, private readonly GetReorderAlerts $reorder, private readonly GetExpiryAlerts $expiry,
        private readonly GetBreedingCalendar $breeding, private readonly GetProductionSummary $production, private readonly GetOutstandingBalances $balances,
        private readonly GetReceivablesPayables $ageing, private readonly GetTrialBalance $trialBalance, private readonly ResolveSettings $settings,
    ) {}

    /** @return Collection<int, array{key: string, severity: string, area: string, module: string, message: string}> */
    public function __invoke(?User $for = null): Collection
    {
        $alerts = collect()
            ->concat($this->healthAlerts())->concat($this->inventoryAlerts())->concat($this->breedingAlerts())
            ->concat($this->productionAlerts())->concat($this->salesAlerts())->concat($this->financeAlerts())->concat($this->taskAlerts());

        return $alerts->when($for, fn (Collection $a) => $a->filter(fn ($alert) => $for->is_active && $for->can("{$alert['module']}.view")))
            ->sortBy(fn ($a) => self::ORDER[$a['severity']])->values();
    }

    /** @return array{key: string, severity: string, area: string, module: string, message: string} */
    private function alert(string $key, string $severity, string $area, string $module, string $message): array
    {
        return compact('key', 'severity', 'area', 'module', 'message');
    }

    /** @return list<array<string, string>> */
    private function healthAlerts(): array
    {
        return ($this->health)()->map(fn ($a) => $this->alert('health:'.$a['type'].':'.substr(sha1($a['message']), 0, 12), $a['severity'], 'Health', 'health', $a['message']))->all();
    }

    /** @return list<array<string, string>> */
    private function inventoryAlerts(): array
    {
        $alerts = [];

        foreach (($this->reorder)() as $r) {
            $out = bccomp($r['on_hand'], '0', 3) <= 0;
            $alerts[] = $this->alert("stock:reorder:{$r['item']->id}", $out ? 'danger' : 'warning', 'Stock', 'inventory', ($out ? 'Out of stock: ' : 'Low stock: ')."{$r['item']->name}, {$r['on_hand']} left (reorder at {$r['reorder_level']})");
        }

        foreach (($this->expiry)() as $e) {
            $alerts[] = $this->alert('stock:expiry:'.$e['batch']->id.($e['expired'] ? ':expired' : ''), $e['expired'] ? 'danger' : 'warning', 'Stock', 'inventory',
                "{$e['batch']->item->name} batch {$e['batch']->batch_number} ".($e['expired'] ? 'has expired' : "expires in {$e['days_left']} days")." ({$e['on_hand']} on hand)");
        }

        return $alerts;
    }

    /** Sows needing attention today are warnings; what is coming in the next three days is information. */
    private function breedingAlerts(): array
    {
        $today = now()->startOfDay();

        return ($this->breeding)($today, $today->copy()->addDays(3))->map(fn ($e) => $this->alert(
            "breeding:{$e['type']}:{$e['sow_id']}:{$e['date']->toDateString()}", $e['date']->lte($today) ? 'warning' : 'info', 'Breeding', 'breeding',
            "{$e['date']->format('d M')}: {$e['type']} - {$e['sow']} ({$e['detail']})",
        ))->all();
    }

    /** @return list<array<string, string>> */
    private function productionAlerts(): array
    {
        $limit = (string) $this->settings->get('production.batch_mortality_alert_percent');
        $stale = (int) $this->settings->get('tasks.weigh_in_overdue_days');
        $alerts = [];

        foreach (($this->production)() as $row) {
            [$batch, $p] = [$row['batch'], $row['performance']];

            if ($p['mortality_percent'] !== null && bccomp((string) $p['mortality_percent'], $limit, 2) > 0) {
                $alerts[] = $this->alert("production:mortality:{$batch->id}", 'danger', 'Production', 'production', "{$batch->code}: {$p['mortality']} of {$p['placed']} pigs have died ({$p['mortality_percent']}%, limit {$limit}%)");
            }

            $last = $p['latest_weigh_in'] ? now()->parse($p['latest_weigh_in']) : $batch->started_on;

            if ($stale > 0 && $last->startOfDay()->diffInDays(now()->startOfDay()) > $stale) {
                $alerts[] = $this->alert("production:weigh:{$batch->id}", 'warning', 'Production', 'production', "{$batch->code} has not been weighed since {$last->format('d M Y')}");
            }
        }

        return $alerts;
    }

    /** @return list<array<string, string>> */
    private function salesAlerts(): array
    {
        $alerts = [];

        foreach (($this->balances)() as $b) {
            $name = $b['customer']->name;

            if ($b['credit_limit'] > 0 && $b['outstanding'] > $b['credit_limit']) {
                $alerts[] = $this->alert("sales:limit:{$b['customer']->id}", 'danger', 'Sales', 'sales', "{$name} owes more than the credit limit");
            }

            if ($b['overdue'] > 0) {
                $alerts[] = $this->alert("sales:overdue:{$b['customer']->id}", 'warning', 'Sales', 'sales', "{$name} has overdue invoices");
            }
        }

        return $alerts;
    }

    /** @return list<array<string, string>> */
    private function financeAlerts(): array
    {
        $alerts = [];
        $ageing = ($this->ageing)();

        $ageing['receivables']['buckets']['over_60'] > 0 && $alerts[] = $this->alert('finance:receivables-60', 'danger', 'Finance', 'finance', 'Customers owe money more than 60 days overdue');
        $ageing['payables']['buckets']['over_60'] > 0 && $alerts[] = $this->alert('finance:payables-60', 'warning', 'Finance', 'finance', 'Supplier invoices are more than 60 days overdue');
        ! ($this->trialBalance)()['balanced'] && $alerts[] = $this->alert('finance:unbalanced', 'danger', 'Finance', 'finance', 'The books do not balance');

        return $alerts;
    }

    /** @return list<array<string, string>> */
    private function taskAlerts(): array
    {
        $overdue = Task::whereIn('status', ['open', 'in_progress'])->whereDate('due_on', '<', now()->toDateString())->get();

        if ($overdue->isEmpty()) {
            return [];
        }

        $urgent = $overdue->contains(fn (Task $t) => in_array($t->priority, [TaskPriority::High, TaskPriority::Urgent], true));

        return [$this->alert('tasks:overdue', $urgent ? 'danger' : 'warning', 'Tasks', 'tasks', $overdue->count().' overdue task'.($overdue->count() === 1 ? '' : 's').($urgent ? ', including high-priority work' : ''))];
    }
}
