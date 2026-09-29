<?php

namespace App\Domain\Breeding\Events;

use App\Domain\Breeding\Models\PregnancyCheck;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class PregnancyConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly PregnancyCheck $record) {}
}
