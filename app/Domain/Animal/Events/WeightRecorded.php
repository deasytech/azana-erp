<?php

namespace App\Domain\Animal\Events;

use App\Domain\Animal\Models\WeightRecord;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class WeightRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly WeightRecord $record) {}
}
