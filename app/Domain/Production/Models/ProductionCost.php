<?php

namespace App\Domain\Production\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\ProductionCostCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionCost extends Model
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
            'incurred_on' => 'date',
            'category' => ProductionCostCategory::class,
            'amount_minor' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
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
