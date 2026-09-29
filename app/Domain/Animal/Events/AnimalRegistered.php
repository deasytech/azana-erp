<?php

namespace App\Domain\Animal\Events;

use App\Domain\Animal\Models\Animal;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class AnimalRegistered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Animal $record) {}
}
