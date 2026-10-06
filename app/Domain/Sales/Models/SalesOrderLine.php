<?php

namespace App\Domain\Sales\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\SalesLineKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => SalesLineKind::class,
            'quantity' => 'decimal:3',
            'heads' => 'integer',
            'unit_price_minor' => 'integer',
            'discount_percent' => 'decimal:2',
            'line_total_minor' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function semenBatch(): BelongsTo
    {
        return $this->belongsTo(SemenBatch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }
}
