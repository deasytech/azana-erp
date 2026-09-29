<?php

namespace App\Domain\Farm\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/** Normalises the human-readable business identifier (code) to trimmed upper case. */
trait HasBusinessCode
{
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtoupper(trim($value)));
    }
}
