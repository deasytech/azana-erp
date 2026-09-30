<?php

namespace App\Domain\Breeding\Events;

use App\Domain\Breeding\Models\Farrowing;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class FarrowingRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Farrowing $record) {}
}
