<?php

namespace App\Domain\Website\Models;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\System\Concerns\Auditable;
use App\Enums\ListingKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    use Auditable;

    protected $table = 'website_listings';

    protected $guarded = [];

    protected $attributes = ['is_published' => false, 'show_price' => false, 'sort_order' => 100];

    protected function casts(): array
    {
        return ['kind' => ListingKind::class, 'is_published' => 'boolean', 'show_price' => 'boolean', 'sort_order' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }
}
