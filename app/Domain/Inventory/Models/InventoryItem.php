<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Models\FeedType;
use App\Domain\System\Concerns\Auditable;
use App\Enums\InventoryCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected $attributes = ['is_active' => true, 'tracks_batches' => false, 'tracks_expiry' => false];

    protected function casts(): array
    {
        return [
            'category' => InventoryCategory::class,
            'tracks_batches' => 'boolean',
            'tracks_expiry' => 'boolean',
            'is_active' => 'boolean',
            'reorder_level' => 'decimal:3',
            'reorder_quantity' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        // Expiry is a property of a batch, so an item that expires must be tracked by batch.
        static::saving(function (self $item) {
            if ($item->tracks_expiry) {
                $item->tracks_batches = true;
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function isInUse(): bool
    {
        return InventoryTransaction::where('inventory_item_id', $this->id)->exists();
    }
}
