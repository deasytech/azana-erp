<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Health\Models\QuarantineRecord;
use App\Domain\Semen\Events\SemenBatchCollected;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Semen\Models\SemenBoar;
use App\Domain\Semen\Models\SemenCollection;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\SemenBoarStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records an ejaculate collected from a boar in the programme. Every collection creates its batch at once
 * (awaiting QC), numbered like IPA-SM-DUR-20260904-001 and expiring after the configured shelf life.
 */
class RecordSemenCollection
{
    public function __construct(
        private readonly NextNumber $nextNumber,
        private readonly ResolveSettings $settings,
    ) {}

    /** @param array{colour?: ?string, odour?: ?string, ph?: ?string, technician_name?: ?string, notes?: ?string} $details */
    public function __invoke(Animal|int $boar, CarbonInterface $collectedAt, string $volumeMl, array $details = [], ?User $actor = null, ?string $idempotencyKey = null): SemenBatch
    {
        return DB::transaction(function () use ($boar, $collectedAt, $volumeMl, $details, $actor, $idempotencyKey) {
            $animal = Animal::lockForUpdate()->with(['category', 'breed'])->findOrFail($boar instanceof Animal ? $boar->id : $boar);

            if ($idempotencyKey && ($existing = SemenCollection::firstWhere('idempotency_key', $idempotencyKey))) {
                return $existing->animal_id === $animal->id ? $existing->batch : throw new DomainException('This idempotency key was already used for a different boar.', 'idempotency_conflict');
            }

            $programme = $this->validate($animal, $collectedAt, $volumeMl, $details);

            $collection = SemenCollection::create([
                'animal_id' => $animal->id,
                'collected_at' => $collectedAt,
                'volume_ml' => $volumeMl,
                'colour' => $details['colour'] ?? null,
                'odour' => $details['odour'] ?? null,
                'ph' => $details['ph'] ?? null,
                'technician_name' => $details['technician_name'] ?? null,
                'notes' => $details['notes'] ?? null,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $batch = SemenBatch::create([
                'number' => $this->number($animal, $collectedAt),
                'semen_collection_id' => $collection->id,
                'animal_id' => $animal->id,
                'breed_id' => $animal->breed_id,
                'collected_on' => $collectedAt->copy()->startOfDay(),
                'expiry_date' => $collectedAt->copy()->startOfDay()->addDays((int) $this->settings->get('semen.shelf_life_days')),
            ]);

            SemenBatchCollected::dispatch($batch);

            return $batch;
        });
    }

    private function number(Animal $boar, CarbonInterface $on): string
    {
        $prefix = (string) $this->settings->get('animals.number_prefix');
        $breed = $boar->breed?->code ?? 'UNK';
        $day = $on->format('Ymd');

        return sprintf('%s-SM-%s-%s-%03d', $prefix, $breed, $day, ($this->nextNumber)("semen:{$prefix}:{$breed}:{$day}"));
    }

    /** @param array<string, mixed> $details */
    private function validate(Animal $boar, CarbonInterface $at, string $volume, array $details): SemenBoar
    {
        $programme = SemenBoar::firstWhere('animal_id', $boar->id);

        if (! $boar->isBoar() || ! $boar->isActive() || ! $programme) {
            throw new DomainException('Semen can only be collected from an active boar in the semen programme.', 'invalid_boar');
        }

        if ($programme->status !== SemenBoarStatus::Active) {
            throw new DomainException("{$boar->animal_number} is {$programme->status->value} and is not being collected from.", 'boar_not_collecting');
        }

        if (QuarantineRecord::where('animal_id', $boar->id)->whereNull('released_on')->exists()) {
            throw new DomainException("{$boar->animal_number} is in quarantine or isolation.", 'boar_quarantined');
        }

        if (! preg_match('/^\d{1,4}(\.\d)?$/', $volume) || bccomp($volume, '0.1', 1) < 0 || bccomp($volume, '1000', 1) > 0) {
            throw new DomainException('The volume must be between 0.1 and 1000 ml, with at most 1 decimal.', 'volume');
        }

        if (isset($details['ph']) && (! is_numeric($details['ph']) || $details['ph'] < 0 || $details['ph'] > 14 || ! preg_match('/^\d{1,2}(\.\d)?$/', (string) $details['ph']))) {
            throw new DomainException('The pH must be between 0 and 14.', 'ph');
        }

        if ($at->gt(now()->addMinutes(5)) || ($boar->birth_date && $at->lt($boar->birth_date))) {
            throw new DomainException('The collection cannot be in the future or before the boar was born.', 'collection_date');
        }

        $this->assertRested($boar, $programme, $at);

        return $programme;
    }

    private function assertRested(Animal $boar, SemenBoar $programme, CarbonInterface $at): void
    {
        $minimum = $programme->min_interval_days ?? (int) $this->settings->get('semen.min_collection_interval_days');
        $last = SemenCollection::where('animal_id', $boar->id)->max('collected_at');

        if ($last && abs($at->copy()->startOfDay()->diffInDays(now()->parse($last)->startOfDay())) < $minimum) {
            throw new DomainException("{$boar->animal_number} was collected on ".now()->parse($last)->format('d M Y')." and needs {$minimum} days between collections.", 'collection_interval');
        }
    }
}
