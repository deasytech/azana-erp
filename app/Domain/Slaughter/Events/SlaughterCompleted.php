<?php

namespace App\Domain\Slaughter\Events;

use App\Domain\Slaughter\Models\Carcass;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A pig has been slaughtered and its carcass recorded. Dispatched only once the surrounding transaction has committed. */
class SlaughterCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Carcass $carcass) {}
}
