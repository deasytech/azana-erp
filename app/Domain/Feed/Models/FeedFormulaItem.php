<?php

namespace App\Domain\Feed\Models;

use App\Domain\Inventory\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedFormulaItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['inclusion_percent' => 'decimal:4'];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(FeedFormula::class, 'feed_formula_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
