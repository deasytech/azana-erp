<?php

namespace App\Domain\Health\Actions;

use App\Domain\Health\Models\QuarantineRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class ReleaseQuarantine
{
    public function __invoke(QuarantineRecord $record, CarbonInterface $releasedOn, ?string $notes = null, ?User $actor = null): QuarantineRecord
    {
        if (! $record->isOpen()) {
            throw new DomainException('This animal has already been released.', 'already_released');
        }

        if ($releasedOn->lt($record->started_on) || $releasedOn->gt(now()->addMinutes(5))) {
            throw new DomainException('The release must fall between the start date and today.', 'release_date');
        }

        $record->forceFill([
            'released_on' => $releasedOn,
            'released_by' => ($actor ?? Auth::user())?->getKey(),
            'release_notes' => $notes,
        ])->save();

        return $record;
    }
}
