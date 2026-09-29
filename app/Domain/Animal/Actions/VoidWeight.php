<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Models\WeightRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class VoidWeight
{
    public function __invoke(WeightRecord $record, string $reason, ?User $actor = null): WeightRecord
    {
        if ($record->isVoided()) {
            throw new DomainException('This weight is already voided.', 'weight_voided');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void a weight.', 'reason_required');
        }

        $record->forceFill([
            'voided_at' => now(),
            'voided_by' => ($actor ?? Auth::user())?->getKey(),
            'void_reason' => trim($reason),
        ])->save();

        return $record;
    }
}
