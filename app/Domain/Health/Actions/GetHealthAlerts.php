<?php

namespace App\Domain\Health\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Health\Models\HealthEvent;
use App\Domain\Health\Models\MedicineBatch;
use App\Domain\Health\Models\QuarantineRecord;
use App\Domain\Health\Models\VeterinaryVisit;
use App\Enums\HealthEventStatus;
use Illuminate\Support\Collection;

/** Things that need attention now. (Delivery as notifications comes with Phase 15.) */
class GetHealthAlerts
{
    public function __construct(private readonly ResolveSettings $settings, private readonly GetVaccinationsDue $vaccinationsDue) {}

    /** @return Collection<int, array{severity: string, type: string, message: string, animal_id: ?int}> */
    public function __invoke(): Collection
    {
        $today = now()->startOfDay();
        $alerts = collect();
        $add = fn (string $severity, string $type, string $message, ?int $animalId = null) => $alerts->push(compact('severity', 'type', 'message') + ['animal_id' => $animalId]);

        foreach (($this->vaccinationsDue)() as $d) {
            $add($d['overdue'] ? 'danger' : 'warning', 'vaccination', "{$d['animal']->animal_number}: {$d['schedule']->name} ".($d['overdue'] ? 'overdue since' : 'due').' '.$d['due_on']->format('d M Y'), $d['animal']->id);
        }

        $expiry = $today->copy()->addDays((int) $this->settings->get('health.batch_expiry_warning_days'));
        foreach (MedicineBatch::with('medicine')->where('is_active', true)->where('expiry_date', '<=', $expiry)->orderBy('expiry_date')->get() as $b) {
            $expired = $b->expiry_date->lt($today);
            $add($expired ? 'danger' : 'warning', 'batch_expiry', "{$b->medicine->name} batch {$b->batch_number} ".($expired ? 'expired' : 'expires').' '.$b->expiry_date->format('d M Y'));
        }

        $open = $today->copy()->subDays((int) $this->settings->get('health.open_event_alert_days'));
        foreach (HealthEvent::with('animal')->where('status', HealthEventStatus::Open->value)->whereDate('observed_on', '<=', $open)->get() as $e) {
            $add('warning', 'open_case', "{$e->animal->animal_number}: {$e->kind->label()} open since {$e->observed_on->format('d M Y')}", $e->animal_id);
        }

        $quarantine = $today->copy()->subDays((int) $this->settings->get('health.quarantine_alert_days'));
        foreach (QuarantineRecord::with('animal')->whereNull('released_on')->whereDate('started_on', '<=', $quarantine)->get() as $q) {
            $add('warning', 'long_quarantine', "{$q->animal->animal_number} in {$q->type->value} since {$q->started_on->format('d M Y')}", $q->animal_id);
        }

        foreach (VeterinaryVisit::whereNotNull('follow_up_on')->whereDate('follow_up_on', '<=', $today)->whereDate('follow_up_on', '>=', $today->copy()->subDays(14))->get() as $v) {
            $add('info', 'vet_follow_up', "Vet follow-up due {$v->follow_up_on->format('d M Y')}: {$v->reason}");
        }

        return $alerts->sortBy(fn ($a) => ['danger' => 0, 'warning' => 1, 'info' => 2][$a['severity']])->values();
    }
}
