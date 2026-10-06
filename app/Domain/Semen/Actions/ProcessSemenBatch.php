<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\SemenBatchStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records how many doses a passed ejaculate was made into (diluted and packed). The count cannot exceed what the
 * ejaculate can yield: volume x concentration x motility, divided by the motile sperm in one dose.
 */
class ProcessSemenBatch
{
    public function __construct(private readonly ResolveSettings $settings) {}

    /** Most doses the batch's ejaculate can be made into, from its QC figures. */
    public function maxDoses(SemenBatch $batch): int
    {
        $qc = $batch->latestQc ?? $batch->qcRecords()->latest('id')->first();

        if (! $qc) {
            return 0;
        }

        $perDose = (string) $this->settings->get('semen.sperm_per_dose_million');

        if (bccomp($perDose, '1', 0) < 0) {
            throw new DomainException('Set the motile sperm per dose (semen settings) to 1 million or more before processing semen.', 'sperm_per_dose');
        }

        $motileSperm = bcmul(bcmul((string) $batch->collection->volume_ml, (string) $qc->concentration_million_per_ml, 4), bcdiv((string) $qc->motility_percent, '100', 6), 4);

        return (int) bcdiv($motileSperm, $perDose, 0);
    }

    public function __invoke(SemenBatch $batch, int $doses, string $doseVolumeMl, ?string $diluent = null, ?User $actor = null): SemenBatch
    {
        return DB::transaction(function () use ($batch, $doses, $doseVolumeMl, $diluent, $actor) {
            $batch = SemenBatch::lockForUpdate()->with(['collection', 'qcRecords'])->findOrFail($batch->id);

            if ($batch->status !== SemenBatchStatus::Passed) {
                throw new DomainException("{$batch->number} is {$batch->status->label()}: only a batch that passed QC can be processed.", 'batch_not_passed');
            }

            if ($batch->isExpired()) {
                throw new DomainException("{$batch->number} has expired.", 'batch_expired');
            }

            if (! preg_match('/^\d{1,4}(\.\d)?$/', $doseVolumeMl) || bccomp($doseVolumeMl, '0.1', 1) < 0) {
                throw new DomainException('The dose volume must be a positive number of ml, with at most 1 decimal.', 'dose_volume');
            }

            $max = $this->maxDoses($batch);

            if ($doses < 1 || $doses > $max) {
                throw new DomainException("This ejaculate can be made into between 1 and {$max} doses.", 'dose_count');
            }

            $batch->update([
                'doses_produced' => $doses, 'dose_volume_ml' => $doseVolumeMl, 'diluent' => $diluent,
                'processed_by' => ($actor ?? Auth::user())?->getKey(), 'processed_at' => now(),
            ]);

            return $batch;
        });
    }
}
