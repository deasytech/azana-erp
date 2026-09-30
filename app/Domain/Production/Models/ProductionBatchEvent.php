<?php

namespace App\Domain\Production\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\BatchEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBatchEvent extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => BatchEventType::class,
            'occurred_on' => 'date',
            'delta' => 'integer',
            'unit_cost_minor' => 'integer',
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

    public function cause(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'cause_id');
    }
}
