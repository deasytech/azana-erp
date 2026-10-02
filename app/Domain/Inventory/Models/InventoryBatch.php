<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Supplier\Models\Supplier;
use App\Domain\System\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBatch extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'manufactured_on' => 'date',
            'expiry_date' => 'date',
            'received_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Expired when the expiry date is before the given day. */
    public function isExpiredOn(CarbonInterface $date): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt($date->copy()->startOfDay());
    }
}
