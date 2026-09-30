<?php

namespace App\Domain\Production\Models;

use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\System\Concerns\Auditable;
use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'status' => BatchStatus::class,
            'started_on' => 'date',
            'closed_on' => 'date',
            'target_weight_kg' => 'decimal:2',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'stage_id');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function pen(): BelongsTo
    {
        return $this->belongsTo(Pen::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductionBatchEvent::class, 'production_batch_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProductionBatchAnimal::class, 'production_batch_id');
    }

    public function weighIns(): HasMany
    {
        return $this->hasMany(BatchWeighIn::class, 'production_batch_id');
    }

    public function feedRecords(): HasMany
    {
        return $this->hasMany(FeedConsumptionRecord::class, 'production_batch_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ProductionCost::class, 'production_batch_id');
    }

    /** Pigs currently in the batch: the sum of its head-count ledger. */
    public function headCount(): int
    {
        return (int) $this->events()->sum('delta');
    }

    public function isActive(): bool
    {
        return $this->status === BatchStatus::Active;
    }

    public function isInUse(): bool
    {
        return true; // batches are history: closed, never deleted
    }
}
