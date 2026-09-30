<?php

namespace App\Domain\Production\Actions;

use App\Domain\Production\Models\ProductionCost;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class VoidProductionCost
{
    public function __invoke(ProductionCost $cost, string $reason, ?User $actor = null): ProductionCost
    {
        if ($cost->isVoided()) {
            throw new DomainException('This cost is already voided.', 'already_voided');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void a cost.', 'reason_required');
        }

        $cost->forceFill(['voided_at' => now(), 'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason)])->save();

        return $cost;
    }
}
