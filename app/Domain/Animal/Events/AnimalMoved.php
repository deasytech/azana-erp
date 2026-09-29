<?php

namespace App\Domain\Animal\Events;

use App\Domain\Animal\Models\AnimalMovement;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class AnimalMoved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly AnimalMovement $record) {}
}
