<?php

namespace App\Domain\Production\Actions;

use App\Domain\Production\Models\ProductionBatch;
use App\Enums\BatchStatus;
use Illuminate\Support\Collection;

/** Key production figures for every active batch, for the production overview. */
class GetProductionSummary
{
    public function __construct(private readonly GetBatchPerformance $performance) {}

    /** @return Collection<int, array{batch: ProductionBatch, performance: array<string, int|string|null>}> */
    public function __invoke(): Collection
    {
        return ProductionBatch::with(['stage', 'pen'])->where('status', BatchStatus::Active->value)->orderBy('started_on')->get()
            ->map(fn (ProductionBatch $batch) => ['batch' => $batch, 'performance' => ($this->performance)($batch)]);
    }
}
