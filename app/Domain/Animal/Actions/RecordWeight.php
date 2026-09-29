<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Events\WeightRecorded;
use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\WeightRecord;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class RecordWeight
{
    public function __construct(private readonly ResolveSettings $settings) {}

    public function __invoke(
        Animal $animal,
        string $weightKg,
        ?CarbonInterface $weighedAt = null,
        string $method = 'scale',
        ?string $notes = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
    ): WeightRecord {
        if ($idempotencyKey && ($existing = WeightRecord::firstWhere('idempotency_key', $idempotencyKey))) {
            return $existing;
        }

        $animal->refresh(); // never trust a stale status
        $weighedAt ??= now();
        $this->validate($animal, $weightKg, $weighedAt);

        $record = $animal->weights()->create([
            'weight_kg' => $weightKg,
            'weighed_at' => $weighedAt,
            'method' => $method,
            'notes' => $notes,
            'user_id' => ($actor ?? Auth::user())?->getKey(),
            'idempotency_key' => $idempotencyKey,
        ]);

        WeightRecorded::dispatch($record);

        return $record;
    }

    private function validate(Animal $animal, string $weightKg, CarbonInterface $weighedAt): void
    {
        $max = (string) $this->settings->get('animals.max_weight_kg');

        if (! preg_match('/^\d{1,6}(\.\d{1,2})?$/', $weightKg) || bccomp($weightKg, '0', 2) <= 0) {
            throw new DomainException('Weight must be a positive number with at most 2 decimals.', 'weight_invalid');
        }

        if (bccomp($weightKg, $max, 2) > 0) {
            throw new DomainException("Weight exceeds the maximum plausible weight of {$max} kg.", 'weight_implausible');
        }

        if (! $animal->isActive()) {
            throw new DomainException("{$animal->animal_number} is {$animal->status->label()}; weights can no longer be recorded.", 'animal_not_active');
        }

        if ($weighedAt->gt(now()->addMinutes(5))) {
            throw new DomainException('A weight cannot be dated in the future.', 'weight_future');
        }

        if ($animal->birth_date && $weighedAt->lt($animal->birth_date)) {
            throw new DomainException('A weight cannot be dated before the animal was born.', 'weight_before_birth');
        }
    }
}
