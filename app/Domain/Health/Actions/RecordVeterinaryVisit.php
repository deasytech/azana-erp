<?php

namespace App\Domain\Health\Actions;

use App\Domain\Health\Models\VeterinaryVisit;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class RecordVeterinaryVisit
{
    /** @param array{veterinarian_id?: ?int, veterinarian_name?: ?string, findings?: ?string, recommendations?: ?string, follow_up_on?: ?CarbonInterface, cost_minor?: ?int} $details */
    public function __invoke(CarbonInterface $visitedOn, string $reason, array $details = [], ?User $actor = null): VeterinaryVisit
    {
        if ($visitedOn->gt(now()->addMinutes(5))) {
            throw new DomainException('A visit cannot be dated in the future.', 'visit_future');
        }

        if (trim($reason) === '' || (empty($details['veterinarian_id']) && trim((string) ($details['veterinarian_name'] ?? '')) === '')) {
            throw new DomainException('Record the reason for the visit and who the veterinarian was.', 'visit_incomplete');
        }

        $followUp = $details['follow_up_on'] ?? null;

        if ($followUp && $followUp->lt($visitedOn)) {
            throw new DomainException('The follow-up cannot be before the visit.', 'follow_up_date');
        }

        return VeterinaryVisit::create([
            'visited_on' => $visitedOn,
            'reason' => trim($reason),
            'veterinarian_id' => $details['veterinarian_id'] ?? null,
            'veterinarian_name' => $details['veterinarian_name'] ?? null,
            'findings' => $details['findings'] ?? null,
            'recommendations' => $details['recommendations'] ?? null,
            'follow_up_on' => $followUp,
            'cost_minor' => $details['cost_minor'] ?? null,
            'user_id' => ($actor ?? Auth::user())?->getKey(),
        ]);
    }
}
