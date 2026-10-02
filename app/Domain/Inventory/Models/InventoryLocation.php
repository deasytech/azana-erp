<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Farm\Models\Location;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLocation extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function farmLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function isInUse(): bool
    {
        return InventoryTransaction::where('inventory_location_id', $this->id)->exists() || StockCount::where('inventory_location_id', $this->id)->exists();
    }
}
