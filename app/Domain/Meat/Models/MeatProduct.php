<?php

namespace App\Domain\Meat\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\System\Concerns\Auditable;
use App\Enums\MeatProductKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeatProduct extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['kind' => MeatProductKind::class, 'is_active' => 'boolean', 'shelf_life_days' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function isInUse(): bool
    {
        return MeatProductionLine::where('meat_product_id', $this->id)->exists();
    }
}
