<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Semen\Models\SemenQcRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\SemenBatchStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records the laboratory evaluation of a batch and decides it against the farm's QC standards: motility and
 * concentration at least the minimum, abnormal forms at most the maximum. A batch that meets them is passed and
 * can be processed and released; one that does not is failed and can never be sold.
 */
class RecordSemenQc
{
    public function __construct(private readonly ResolveSettings $settings) {}

    public function __invoke(SemenBatch $batch, string $motilityPercent, string $concentrationMillionPerMl, string $abnormalPercent, ?string $notes = null, ?User $actor = null): SemenQcRecord
    {
        $this->validate($motilityPercent, $concentrationMillionPerMl, $abnormalPercent);

        return DB::transaction(function () use ($batch, $motilityPercent, $concentrationMillionPerMl, $abnormalPercent, $notes, $actor) {
            $batch = SemenBatch::lockForUpdate()->findOrFail($batch->id);

            if ($batch->status !== SemenBatchStatus::PendingQc) {
                throw new DomainException("{$batch->number} is {$batch->status->label()}: QC is recorded once, while a batch is pending QC.", 'batch_not_pending');
            }

            if ($batch->isExpired()) {
                throw new DomainException("{$batch->number} has expired and can no longer be evaluated.", 'batch_expired');
            }

            $problems = array_values(array_filter([
                bccomp($motilityPercent, (string) $this->settings->get('semen.min_motility_percent'), 2) < 0 ? "motility {$motilityPercent}% is below {$this->settings->get('semen.min_motility_percent')}%" : null,
                bccomp($concentrationMillionPerMl, (string) $this->settings->get('semen.min_concentration_million_per_ml'), 2) < 0 ? "concentration {$concentrationMillionPerMl} is below {$this->settings->get('semen.min_concentration_million_per_ml')} million/ml" : null,
                bccomp($abnormalPercent, (string) $this->settings->get('semen.max_abnormal_percent'), 2) > 0 ? "abnormal forms {$abnormalPercent}% are above {$this->settings->get('semen.max_abnormal_percent')}%" : null,
            ]));

            $record = SemenQcRecord::create([
                'semen_batch_id' => $batch->id,
                'motility_percent' => $motilityPercent,
                'concentration_million_per_ml' => $concentrationMillionPerMl,
                'abnormal_percent' => $abnormalPercent,
                'passed' => $problems === [],
                'failed_because' => $problems === [] ? null : ucfirst(implode('; ', $problems)),
                'notes' => $notes,
                'evaluated_by' => ($actor ?? Auth::user())?->getKey(),
                'evaluated_at' => now(),
            ]);

            $batch->update(['status' => $problems === [] ? SemenBatchStatus::Passed : SemenBatchStatus::Failed]);

            return $record;
        });
    }

    private function validate(string $motility, string $concentration, string $abnormal): void
    {
        foreach ([$motility, $abnormal] as $percent) {
            if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $percent) || bccomp($percent, '100', 2) > 0) {
                throw new DomainException('Motility and abnormal forms must be percentages from 0 to 100.', 'qc_percent');
            }
        }

        if (! preg_match('/^\d{1,6}(\.\d{1,2})?$/', $concentration)) {
            throw new DomainException('The concentration must be a number of million sperm per ml.', 'qc_concentration');
        }
    }
}
