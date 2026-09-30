<?php

namespace App\Domain\Production\Actions;

use App\Domain\Production\Models\BatchWeighIn;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class VoidBatchWeighIn
{
    public function __invoke(BatchWeighIn $weighIn, string $reason, ?User $actor = null): BatchWeighIn
    {
        if ($weighIn->isVoided()) {
            throw new DomainException('This weigh-in is already voided.', 'already_voided');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void a weigh-in.', 'reason_required');
        }

        $weighIn->forceFill(['voided_at' => now(), 'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason)])->save();

        return $weighIn;
    }
}
