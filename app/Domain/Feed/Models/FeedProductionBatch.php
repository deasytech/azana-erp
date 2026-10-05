<?php

namespace App\Domain\Feed\Models;

use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedProductionBatch extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** Only reversing may change a finished batch. */
    public function mutableColumns(): array
    {
        return ['reversed_at', 'reversed_by', 'reverse_reason'];
    }

    protected function casts(): array
    {
        return [
            'output_kg' => 'decimal:3',
            'produced_on' => 'date',
            'material_cost_minor' => 'integer',
            'other_cost_minor' => 'integer',
            'total_cost_minor' => 'integer',
            'cost_per_kg_minor' => 'integer',
            'reversed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FeedProductionOrder::class, 'feed_production_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function producedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'produced_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
