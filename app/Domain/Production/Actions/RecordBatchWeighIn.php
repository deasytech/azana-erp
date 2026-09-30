<?php

namespace App\Domain\Production\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Production\Models\BatchWeighIn;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Records the average weight of a sample of the batch. Growth (ADG, FCR) is calculated from valid weigh-ins only. */
class RecordBatchWeighIn
{
    public function __construct(private readonly ResolveSettings $settings) {}

    public function __invoke(ProductionBatch $batch, CarbonInterface $weighedOn, int $sampleSize, string $averageWeightKg, ?string $notes = null, ?User $actor = null, ?string $idempotencyKey = null): BatchWeighIn
    {
        return DB::transaction(function () use ($batch, $weighedOn, $sampleSize, $averageWeightKg, $notes, $actor, $idempotencyKey) {
            $batch = ProductionBatch::lockForUpdate()->findOrFail($batch->id);

            if ($idempotencyKey && ($existing = BatchWeighIn::firstWhere('idempotency_key', $idempotencyKey))) {
                return $existing->production_batch_id === $batch->id
                    ? $existing
                    : throw new DomainException('This idempotency key was already used for a different batch.', 'idempotency_conflict');
            }

            $this->validate($batch, $weighedOn, $sampleSize, $averageWeightKg);

            return $batch->weighIns()->create([
                'weighed_on' => $weighedOn,
                'sample_size' => $sampleSize,
                'average_weight_kg' => $averageWeightKg,
                'notes' => $notes,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    private function validate(ProductionBatch $batch, CarbonInterface $on, int $sample, string $weight): void
    {
        $max = (string) $this->settings->get('animals.max_weight_kg');

        if (! preg_match('/^\d{1,6}(\.\d{1,2})?$/', $weight) || bccomp($weight, '0', 2) <= 0) {
            throw new DomainException('The average weight must be a positive number with at most 2 decimals.', 'weight_invalid');
        }

        if (bccomp($weight, $max, 2) > 0) {
            throw new DomainException("The weight exceeds the maximum plausible weight of {$max} kg.", 'weight_implausible');
        }

        if ($on->gt(now()->addMinutes(5)) || $on->lt($batch->started_on)) {
            throw new DomainException('The weigh-in must fall between the batch start and today.', 'weigh_in_date');
        }

        $heads = (int) $batch->events()->whereDate('occurred_on', '<=', $on->toDateString())->sum('delta');

        if ($sample < 1 || $sample > $heads) {
            throw new DomainException("The sample must be between 1 and the {$heads} pigs in the batch on that date.", 'sample_size');
        }

        if ($batch->weighIns()->whereNull('voided_at')->whereDate('weighed_on', $on->toDateString())->exists()) {
            throw new DomainException('This batch already has a weigh-in for that day (void it first to replace it).', 'weigh_in_duplicate');
        }
    }
}
