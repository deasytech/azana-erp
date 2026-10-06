<?php

namespace App\Domain\Semen\Events;

use App\Domain\Semen\Models\SemenBatch;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class SemenBatchReleased implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly SemenBatch $batch) {}
}
