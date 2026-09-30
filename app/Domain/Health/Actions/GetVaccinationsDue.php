<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Health\Models\VaccinationSchedule;
use App\Enums\AnimalStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Vaccinations that are due or overdue for active animals, from the active schedules: the first dose falls due
 * at the schedule's age (needs a known birth date), boosters at the repeat interval after the last dose
 * (which does not).
 */
class GetVaccinationsDue
{
    public function __construct(private readonly ResolveSettings $settings) {}

    /** @return Collection<int, array{animal: Animal, schedule: VaccinationSchedule, due_on: CarbonInterface, overdue: bool}> */
    public function __invoke(?CarbonInterface $today = null, ?int $leadDays = null): Collection
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $horizon = $today->copy()->addDays($leadDays ?? (int) $this->settings->get('health.vaccination_reminder_days'));
        $due = collect();

        foreach (VaccinationSchedule::with('medicine')->where('is_active', true)->get() as $schedule) {
            $last = Vaccination::where('vaccination_schedule_id', $schedule->id)->groupBy('animal_id')
                ->selectRaw('animal_id, max(administered_on) as last_on')->pluck('last_on', 'animal_id');

            Animal::where('status', AnimalStatus::Active->value)
                ->when($schedule->category_id, fn ($q, $category) => $q->where('category_id', $category))
                ->get()
                ->each(function (Animal $animal) use ($schedule, $last, $horizon, $today, $due) {
                    $dueOn = $this->dueDate($animal, $schedule, $last[$animal->id] ?? null);

                    if ($dueOn && $dueOn->lte($horizon)) {
                        $due->push(['animal' => $animal, 'schedule' => $schedule, 'due_on' => $dueOn, 'overdue' => $dueOn->lt($today)]);
                    }
                });
        }

        return $due->sortBy(fn ($d) => $d['due_on']->getTimestamp())->values();
    }

    private function dueDate(Animal $animal, VaccinationSchedule $schedule, ?string $lastOn): ?CarbonInterface
    {
        if ($lastOn === null) {
            // The first dose is scheduled by age, so it needs a known birth date.
            return $animal->birth_date?->copy()->addDays($schedule->first_dose_age_days);
        }

        return $schedule->repeat_interval_days
            ? Carbon::parse($lastOn)->startOfDay()->addDays($schedule->repeat_interval_days)
            : null; // single-dose schedule, already given
    }
}
