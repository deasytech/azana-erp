<?php

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Money has been received from a customer. Dispatched only once the surrounding transaction has committed. */
class PaymentReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
