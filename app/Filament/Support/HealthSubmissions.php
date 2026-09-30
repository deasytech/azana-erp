<?php

namespace App\Filament\Support;

use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Actions\RecordCulling;
use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Health\Actions\RecordTreatment;
use App\Domain\Health\Actions\RecordVaccination;
use App\Domain\Health\Actions\ReportHealthEvent;
use App\Domain\Health\Actions\StartQuarantine;
use App\Domain\Health\Models\Medicine;
use App\Enums\CullHealthStatus;
use App\Enums\DisposalType;
use App\Enums\HealthEventKind;
use App\Enums\HealthSeverity;
use App\Enums\QuarantineType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** Hands the shared health forms' data to the domain actions (one place for the form-to-action mapping). */
class HealthSubmissions
{
    /** @param array<string, mixed> $d */
    public static function treat(Animal $animal, array $d): Model
    {
        return app(RecordTreatment::class)($animal, Medicine::findOrFail($d['medicine_id']), Carbon::parse($d['administered_on']), [
            'batch_id' => $d['batch_id'] ?? null,
            'dose' => filled($d['dose'] ?? null) ? (string) $d['dose'] : null,
            'dose_unit' => $d['dose_unit'] ?? null,
            'route' => $d['route'] ?? null,
            'withdrawal_days' => filled($d['withdrawal_days'] ?? null) ? (int) $d['withdrawal_days'] : null,
            'notes' => $d['notes'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $d */
    public static function vaccinate(Animal $animal, array $d): Model
    {
        return app(RecordVaccination::class)($animal, Carbon::parse($d['administered_on']), [
            'schedule_id' => $d['schedule_id'] ?? null,
            'medicine_id' => $d['medicine_id'] ?? null,
            'batch_id' => $d['batch_id'] ?? null,
            'dose' => filled($d['dose'] ?? null) ? (string) $d['dose'] : null,
            'notes' => $d['notes'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $d */
    public static function reportCase(Animal $animal, array $d): Model
    {
        return app(ReportHealthEvent::class)($animal, HealthEventKind::from($d['kind']), HealthSeverity::from($d['severity']), Carbon::parse($d['observed_on']), $d['symptoms'] ?? null, $d['disease_id'] ?? null);
    }

    /** @param array<string, mixed> $d */
    public static function quarantine(Animal $animal, array $d): Model
    {
        return app(StartQuarantine::class)($animal, QuarantineType::from($d['type']), Carbon::parse($d['started_on']), $d['reason'], $d['pen_id'] ?? null, $d['location_id'] ?? null);
    }

    /** @param array<string, mixed> $d */
    public static function recordDeath(Animal $animal, array $d): Model
    {
        return app(RecordMortality::class)($animal, Carbon::parse($d['died_on']), (int) $d['cause_id'], $d['disease_id'] ?? null, $d['notes'] ?? null);
    }

    /** @param array<string, mixed> $d */
    public static function cull(Animal $animal, array $d): Model
    {
        return app(RecordCulling::class)(
            $animal, Carbon::parse($d['culled_on']), (int) $d['reason_id'], (string) $d['weight_kg'], CullHealthStatus::from($d['health_status']),
            DisposalType::from($d['disposal']), (int) $d['disposal_value_minor'], $d['notes'] ?? null,
        );
    }
}
