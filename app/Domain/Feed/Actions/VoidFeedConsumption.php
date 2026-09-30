<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class VoidFeedConsumption
{
    public function __invoke(FeedConsumptionRecord $record, string $reason, ?User $actor = null): FeedConsumptionRecord
    {
        if ($record->isVoided()) {
            throw new DomainException('This feed record is already voided.', 'already_voided');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required to void a feed record.', 'reason_required');
        }

        $record->forceFill(['voided_at' => now(), 'voided_by' => ($actor ?? Auth::user())?->getKey(), 'void_reason' => trim($reason)])->save();

        return $record;
    }
}
