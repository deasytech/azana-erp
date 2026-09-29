<?php

namespace App\Domain\System\Concerns;

use LogicException;

/**
 * Append-only records: never deleted, and updated only in the columns a model explicitly
 * allows (e.g. voiding). Corrections are made by adding new records.
 */
trait ImmutableRecord
{
    public static function bootImmutableRecord(): void
    {
        static::updating(function ($model) {
            $forbidden = array_diff(array_keys($model->getDirty()), $model->mutableColumns());

            if ($forbidden !== []) {
                throw new LogicException(class_basename($model).' records are immutable.');
            }
        });

        static::deleting(fn ($model) => throw new LogicException(class_basename($model).' records cannot be deleted.'));
    }

    /** @return list<string> */
    public function mutableColumns(): array
    {
        return [];
    }
}
