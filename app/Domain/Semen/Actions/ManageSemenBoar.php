<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Semen\Models\SemenBoar;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\SemenBoarStatus;

/** Boars in the semen programme: who is collected from, resting or retired, and each boar's own limits. */
class ManageSemenBoar
{
    /** @param array{status?: ?string, min_interval_days?: ?int, target_doses_per_week?: ?int, notes?: ?string} $details */
    public function enrol(Animal|int $animal, array $details = []): SemenBoar
    {
        $animal = Animal::with('category')->findOrFail($animal instanceof Animal ? $animal->id : $animal);

        if (! $animal->isBoar() || ! $animal->isActive()) {
            throw new DomainException('Only an active boar can join the semen programme.', 'invalid_boar');
        }

        if (SemenBoar::where('animal_id', $animal->id)->exists()) {
            throw new DomainException("{$animal->animal_number} is already in the semen programme.", 'boar_enrolled');
        }

        $this->validate($details);

        return SemenBoar::create(['animal_id' => $animal->id] + $this->fields($details));
    }

    /** @param array{status?: ?string, min_interval_days?: ?int, target_doses_per_week?: ?int, notes?: ?string} $details */
    public function update(SemenBoar $boar, array $details): SemenBoar
    {
        $this->validate($details);
        $boar->update($this->fields($details));

        return $boar;
    }

    /** @param array<string, mixed> $details */
    private function validate(array $details): void
    {
        if (isset($details['status']) && SemenBoarStatus::tryFrom((string) $details['status']) === null) {
            throw new DomainException('Choose active, resting or retired.', 'boar_status');
        }

        foreach (['min_interval_days' => 365, 'target_doses_per_week' => 100000] as $field => $max) {
            $value = $details[$field] ?? null;

            if ($value !== null && (! is_numeric($value) || (int) $value != $value || $value < 0 || $value > $max)) {
                throw new DomainException('The interval and target must be whole numbers, zero or more.', 'boar_limits');
            }
        }
    }

    /** @return array<string, mixed> */
    private function fields(array $details): array
    {
        return array_intersect_key($details, array_flip(['status', 'min_interval_days', 'target_doses_per_week', 'notes']));
    }
}
