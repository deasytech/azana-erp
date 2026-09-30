<?php

namespace App\Domain\Health\Events;

use App\Domain\Health\Models\MortalityRecord;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class MortalityRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly MortalityRecord $record) {}
}
