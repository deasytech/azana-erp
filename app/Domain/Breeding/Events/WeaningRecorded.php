<?php

namespace App\Domain\Breeding\Events;

use App\Domain\Litter\Models\WeaningRecord;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class WeaningRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly WeaningRecord $record) {}
}
