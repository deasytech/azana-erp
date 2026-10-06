<?php

namespace App\Domain\Meat\Events;

use App\Domain\Meat\Models\MeatProductionBatch;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Meat has been made from carcasses and put into stock. Dispatched only once the surrounding transaction has committed. */
class MeatProduced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly MeatProductionBatch $batch) {}
}
