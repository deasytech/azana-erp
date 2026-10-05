<?php

namespace App\Domain\Feed\Models;

use App\Domain\Inventory\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedProductionOrderLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['planned_quantity' => 'decimal:3', 'actual_quantity' => 'decimal:3', 'confirmed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FeedProductionOrder::class, 'feed_production_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->actual_quantity !== null;
    }
}
