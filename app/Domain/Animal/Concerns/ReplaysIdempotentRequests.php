<?php

namespace App\Domain\Animal\Concerns;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Model;

/** A retried request (same idempotency key) returns the original record instead of creating another. */
trait ReplaysIdempotentRequests
{
    /**
     * @param  class-string<Model>  $model  a record type with animal_id and idempotency_key columns
     */
    protected function replay(string $model, Animal $animal, ?string $key): ?Model
    {
        $existing = $key ? $model::firstWhere('idempotency_key', $key) : null;

        if ($existing && $existing->animal_id !== $animal->id) {
            throw new DomainException('This idempotency key was already used for a different animal.', 'idempotency_conflict');
        }

        return $existing;
    }
}
