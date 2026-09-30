<?php

namespace App\Domain\Production\Actions;

use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\BatchEventType;
use App\Enums\LookupCategory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Starts a grower/finisher batch with its first placement (and, if the average weight is given, its
 * first weigh-in, which is the starting point for growth calculations).
 *
 * $data keys: name, stage_id, started_on (CarbonInterface), count, average_weight_kg, unit_cost_minor, breed_id,
 * pen_id, placed_age_days, target_weight_kg, source_note, notes.
 */
class OpenProductionBatch
{
    public function __construct(
        private readonly NextNumber $nextNumber,
        private readonly PostBatchEvent $postEvent,
        private readonly RecordBatchWeighIn $weighIn,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(array $data, ?User $actor = null): ProductionBatch
    {
        $this->validate($data);

        return DB::transaction(function () use ($data, $actor) {
            /** @var CarbonInterface $started */
            $started = $data['started_on'];

            $batch = ProductionBatch::create([
                'code' => sprintf('BATCH-%d-%03d', $started->year, ($this->nextNumber)("batch:{$started->year}")),
                'name' => trim($data['name']),
                'stage_id' => $data['stage_id'],
                'breed_id' => $data['breed_id'] ?? null,
                'pen_id' => $data['pen_id'] ?? null,
                'started_on' => $started,
                'placed_age_days' => $data['placed_age_days'] ?? null,
                'target_weight_kg' => $data['target_weight_kg'] ?? null,
                'source_note' => $data['source_note'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);

            ($this->postEvent)($batch, BatchEventType::Placement, (int) $data['count'], $started, [
                'unit_cost_minor' => $data['unit_cost_minor'] ?? null, 'notes' => 'Initial placement',
            ], $actor);

            if (filled($data['average_weight_kg'] ?? null)) {
                ($this->weighIn)($batch, $started, (int) $data['count'], (string) $data['average_weight_kg'], 'Weight at placement', $actor);
            }

            return $batch->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        if (trim((string) ($data['name'] ?? '')) === '') {
            throw new DomainException('Give the batch a name.', 'batch_name');
        }

        if (! LookupValue::where('category', LookupCategory::AnimalCategory->value)->where('is_active', true)->whereKey($data['stage_id'] ?? 0)->exists()) {
            throw new DomainException('Choose a valid production stage.', 'invalid_stage');
        }

        if ((int) ($data['count'] ?? 0) < 1) {
            throw new DomainException('A batch needs at least one pig.', 'batch_count');
        }

        if (! ($data['started_on'] ?? null) instanceof CarbonInterface || $data['started_on']->gt(now()->addMinutes(5))) {
            throw new DomainException('The batch cannot start in the future.', 'batch_start');
        }

        if (! empty($data['breed_id']) && ! Breed::where('is_active', true)->whereKey($data['breed_id'])->exists()) {
            throw new DomainException('Choose a valid breed.', 'invalid_breed');
        }

        if (! empty($data['pen_id']) && ! Pen::where('is_active', true)->whereKey($data['pen_id'])->exists()) {
            throw new DomainException('Choose an active pen.', 'inactive_pen');
        }

        if (isset($data['target_weight_kg']) && bccomp((string) $data['target_weight_kg'], '0', 2) <= 0) {
            throw new DomainException('The target weight must be positive.', 'target_weight');
        }
    }
}
