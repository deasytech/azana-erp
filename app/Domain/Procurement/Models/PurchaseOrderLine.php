<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Inventory\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost_minor' => 'integer', 'line_total_minor' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    /** Quantity received on receipts that have not been voided. */
    public function receivedQuantity(): string
    {
        return bcadd((string) $this->receiptLines()->whereHas('receipt', fn ($q) => $q->whereNull('voided_at'))->sum('quantity'), '0', 3);
    }
}
