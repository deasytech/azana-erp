<?php

namespace App\Domain\Feed\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedConsumptionRecord extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** Only these columns may change after creation. */
    public function mutableColumns(): array
    {
        return ['voided_at', 'voided_by', 'void_reason'];
    }

    protected function casts(): array
    {
        return [
            'consumed_on' => 'date',
            'quantity_kg' => 'decimal:2',
            'cost_per_kg_minor' => 'integer',
            'cost_minor' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class, 'feed_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
