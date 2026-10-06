<?php

namespace App\Domain\Meat\Models;

use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\System\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeatProductionLine extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:2', 'cost_minor' => 'integer', 'use_by' => 'date'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MeatProductionBatch::class, 'meat_production_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MeatProduct::class, 'meat_product_id');
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }
}
