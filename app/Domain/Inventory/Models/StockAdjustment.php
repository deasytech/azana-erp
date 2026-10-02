<?php

namespace App\Domain\Inventory\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use Auditable, ImmutableRecord;

    protected $guarded = [];

    protected $attributes = ['status' => 'pending'];

    /** Only the decision may be recorded after the request is made. */
    public function mutableColumns(): array
    {
        return ['status', 'decided_by', 'decided_at', 'decision_notes', 'updated_at'];
    }

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'quantity' => 'decimal:3',
            'decided_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
