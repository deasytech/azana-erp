<?php

namespace App\Domain\Health\Actions;

use App\Domain\Health\Models\WithdrawalPeriod;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** A vet's early release of a withdrawal period. Always needs a reason; the audit log keeps who and why. */
class ClearWithdrawal
{
    public function __invoke(WithdrawalPeriod $period, string $reason, ?User $actor = null): WithdrawalPeriod
    {
        if ($period->cleared_at !== null) {
            throw new DomainException('This withdrawal period is already cleared.', 'withdrawal_cleared');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required to clear a withdrawal period early.', 'reason_required');
        }

        $period->forceFill([
            'cleared_at' => now(),
            'cleared_by' => ($actor ?? Auth::user())?->getKey(),
            'clear_reason' => trim($reason),
        ])->save();

        return $period;
    }
}
