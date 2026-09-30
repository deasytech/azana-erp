<?php

namespace App\Domain\Health\Actions;

use App\Domain\Health\Models\LaboratoryResult;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class RecordLabResult
{
    /** @param array{animal_id?: ?int, health_event_id?: ?int, veterinary_visit_id?: ?int, resulted_on?: ?CarbonInterface, result?: ?string, is_abnormal?: bool, lab_name?: ?string, notes?: ?string} $details */
    public function __invoke(string $sampleType, string $testName, CarbonInterface $sampledOn, array $details = [], ?User $actor = null): LaboratoryResult
    {
        $resultedOn = $details['resulted_on'] ?? null;

        if (trim($sampleType) === '' || trim($testName) === '') {
            throw new DomainException('Record the sample type and the test.', 'lab_incomplete');
        }

        if ($sampledOn->gt(now()->addMinutes(5)) || ($resultedOn && ($resultedOn->lt($sampledOn) || $resultedOn->gt(now()->addMinutes(5))))) {
            throw new DomainException('Dates must run from sampling to result, and not be in the future.', 'lab_dates');
        }

        return LaboratoryResult::create([
            'animal_id' => $details['animal_id'] ?? null,
            'health_event_id' => $details['health_event_id'] ?? null,
            'veterinary_visit_id' => $details['veterinary_visit_id'] ?? null,
            'sample_type' => trim($sampleType),
            'test_name' => trim($testName),
            'sampled_on' => $sampledOn,
            'resulted_on' => $resultedOn,
            'result' => $details['result'] ?? null,
            'is_abnormal' => $details['is_abnormal'] ?? false,
            'lab_name' => $details['lab_name'] ?? null,
            'notes' => $details['notes'] ?? null,
            'user_id' => ($actor ?? Auth::user())?->getKey(),
        ]);
    }
}
