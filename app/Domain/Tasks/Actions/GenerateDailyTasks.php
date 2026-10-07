<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Breeding\Actions\GetBreedingCalendar;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Health\Actions\GetVaccinationsDue;
use App\Domain\Inventory\Actions\GetExpiryAlerts;
use App\Domain\Inventory\Actions\GetReorderAlerts;
use App\Domain\Production\Actions\GetProductionSummary;
use App\Domain\Sales\Actions\GetOutstandingBalances;
use App\Enums\TaskCategory as C;
use App\Enums\TaskPriority as P;
use Carbon\CarbonInterface;

/**
 * Writes the day's work from the farm's own data: the daily rounds set in settings, vaccinations due, breeding events, low or expiring
 * stock, batches overdue for weighing and customers with overdue invoices. Every task has a source key that includes the day (or week,
 * for chasing payments), so running it again the same day adds nothing. Returns the number of new tasks by source.
 */
class GenerateDailyTasks
{
    public function __construct(
        private readonly CreateTask $create, private readonly ResolveSettings $settings, private readonly GetVaccinationsDue $vaccinations,
        private readonly GetBreedingCalendar $breeding, private readonly GetReorderAlerts $reorder, private readonly GetExpiryAlerts $expiry,
        private readonly GetProductionSummary $production, private readonly GetOutstandingBalances $balances,
    ) {}

    /** @return array<string, int> */
    public function __invoke(?CarbonInterface $day = null): array
    {
        $day = ($day ?? now())->copy()->startOfDay();
        $made = array_fill_keys(['rounds', 'vaccinations', 'breeding', 'stock', 'production', 'sales'], 0);
        $add = function (string $source, string $title, string $key, C $category, string $role, P $priority = P::Normal, ?string $description = null) use ($day, &$made) {
            $task = $this->create->__invoke($title, $day, $category, $priority, $description, null, $role, $key);
            $made[$source] += (int) $task->wasRecentlyCreated;
        };

        foreach (array_filter(array_map('trim', explode(';', (string) $this->settings->get('tasks.daily_rounds')))) as $round) {
            $add('rounds', $round, "round:{$day->toDateString()}:".str($round)->slug(), C::Rounds, 'Farm Worker');
        }

        foreach (($this->vaccinations)($day) as $d) {
            $add('vaccinations', "Vaccinate {$d['animal']->animal_number}: {$d['schedule']->name}", "vaccination:{$d['animal']->id}:{$d['schedule']->id}:{$d['due_on']->toDateString()}", C::Health, 'Farm Manager', $d['overdue'] ? P::High : P::Normal, 'Due '.$d['due_on']->format('d M Y'));
        }

        foreach (($this->breeding)($day, $day) as $e) {
            $add('breeding', "{$e['type']}: {$e['sow']}", "breeding:{$e['type']}:{$e['sow_id']}:{$e['date']->toDateString()}", C::Breeding, 'Breeding Manager', P::Normal, $e['detail']);
        }

        foreach (($this->reorder)() as $r) {
            $add('stock', "Reorder {$r['item']->name}", "reorder:{$r['item']->id}:{$day->toDateString()}", C::Inventory, 'Store Officer', P::Normal, "{$r['on_hand']} left; reorder level {$r['reorder_level']}, suggest {$r['suggested_quantity']}");
        }

        foreach (($this->expiry)() as $e) {
            $add('stock', ($e['expired'] ? 'Remove expired stock: ' : 'Use or move before it expires: ').$e['batch']->item->name.' '.$e['batch']->batch_number, "expiry:{$e['batch']->id}:{$day->toDateString()}", C::Inventory, 'Store Officer', $e['expired'] ? P::High : P::Normal);
        }

        $stale = (int) $this->settings->get('tasks.weigh_in_overdue_days');
        foreach (($this->production)() as $row) {
            $last = $row['performance']['latest_weigh_in'] ? now()->parse($row['performance']['latest_weigh_in']) : $row['batch']->started_on;

            $stale > 0 && $last->startOfDay()->diffInDays($day) > $stale
                && $add('production', "Weigh batch {$row['batch']->code}", "weigh:{$row['batch']->id}:{$day->toDateString()}", C::Production, 'Farm Manager', P::Normal, "Last weighed {$last->format('d M Y')}");
        }

        foreach (($this->balances)() as $b) {
            $b['overdue'] > 0 && $add('sales', "Collect overdue payment from {$b['customer']->name}", "collect:{$b['customer']->id}:{$day->format('o-\\WW')}", C::Sales, 'Sales Officer', P::High);
        }

        return $made;
    }
}
