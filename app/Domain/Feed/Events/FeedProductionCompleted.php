<?php

namespace App\Domain\Feed\Events;

use App\Domain\Feed\Models\FeedProductionBatch;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class FeedProductionCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly FeedProductionBatch $batch) {}
}
