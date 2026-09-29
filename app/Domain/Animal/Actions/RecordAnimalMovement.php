<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Concerns\ReplaysIdempotentRequests;
use App\Domain\Animal\Events\AnimalMoved;
use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalMovement;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\Pen;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Moves an animal into a pen or location, preserving the move permanently.
 * The same action serves the web UI, the API and offline sync.
 */
class RecordAnimalMovement
{
    use ReplaysIdempotentRequests;

    public function __invoke(
        Animal $animal,
        ?int $penId = null,
        ?int $locationId = null,
        ?CarbonInterface $movedAt = null,
        ?int $reasonId = null,
        ?string $notes = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
    ): AnimalMovement {
        if (($penId === null) === ($locationId === null)) {
            throw new DomainException('Choose either a pen or a location as the destination.', 'movement_destination');
        }

        return DB::transaction(function () use ($animal, $penId, $locationId, $movedAt, $reasonId, $notes, $actor, $idempotencyKey) {
            // Lock first: concurrent retries of the same request queue up here, then replay the original.
            $animal = Animal::lockForUpdate()->findOrFail($animal->id);

            if ($existing = $this->replay(AnimalMovement::class, $animal, $idempotencyKey)) {
                return $existing;
            }

            $this->assertCanMove($animal, $penId, $locationId);

            return $this->write($animal, $penId, $locationId, $movedAt, $reasonId, $notes, $actor, $idempotencyKey);
        });
    }

    /** Records the animal leaving the farm (sale, death, slaughter...). Used by status changes. */
    public function exit(Animal $animal, CarbonInterface $at, string $notes, ?User $actor = null): ?AnimalMovement
    {
        if ($animal->current_pen_id === null && $animal->current_location_id === null) {
            return null;
        }

        return $this->write($animal, null, null, $at, null, $notes, $actor, null);
    }

    private function assertCanMove(Animal $animal, ?int $penId, ?int $locationId): void
    {
        if ($animal->status !== AnimalStatus::Active) {
            throw new DomainException("{$animal->animal_number} is {$animal->status->label()} and cannot be moved.", 'animal_not_active');
        }

        if ($penId === $animal->current_pen_id && $locationId === $animal->current_location_id) {
            throw new DomainException("{$animal->animal_number} is already there.", 'movement_same_place');
        }

        if ($penId !== null) {
            $this->assertPenAvailable(Pen::lockForUpdate()->find($penId) ?? throw new DomainException('Unknown pen.', 'unknown_pen'));
        } elseif (! Location::where('is_active', true)->whereKey($locationId)->exists()) {
            throw new DomainException('The destination location does not exist or is inactive.', 'inactive_location');
        }
    }

    private function assertPenAvailable(Pen $pen): void
    {
        if (! $pen->is_active) {
            throw new DomainException("Pen {$pen->code} is inactive.", 'inactive_pen');
        }

        if ($pen->capacity !== null && Animal::where('current_pen_id', $pen->id)->count() >= $pen->capacity) {
            throw new DomainException("Pen {$pen->code} is full ({$pen->capacity} animals).", 'pen_full');
        }
    }

    private function write(Animal $animal, ?int $penId, ?int $locationId, ?CarbonInterface $movedAt, ?int $reasonId, ?string $notes, ?User $actor, ?string $key): AnimalMovement
    {
        $movedAt ??= now();
        $latest = AnimalMovement::where('animal_id', $animal->id)->max('moved_at');

        if ($movedAt->gt(now()->addMinutes(5))) {
            throw new DomainException('A movement cannot be dated in the future.', 'movement_future');
        }

        if ($latest && $movedAt->lt($latest)) {
            throw new DomainException('This is earlier than the animal\'s latest recorded movement.', 'movement_out_of_order');
        }

        $movement = AnimalMovement::create([
            'animal_id' => $animal->id,
            'from_pen_id' => $animal->current_pen_id,
            'from_location_id' => $animal->current_location_id,
            'to_pen_id' => $penId,
            'to_location_id' => $locationId,
            'reason_id' => $reasonId,
            'moved_at' => $movedAt,
            'notes' => $notes,
            'user_id' => ($actor ?? Auth::user())?->getKey(),
            'idempotency_key' => $key,
        ]);

        $animal->forceFill(['current_pen_id' => $penId, 'current_location_id' => $locationId])->save();

        AnimalMoved::dispatch($movement);

        return $movement;
    }
}
