<?php

namespace App\Domain\Meat\Models;

use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\System\Concerns\Auditable;
use App\Enums\MeatProductionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeatProductionBatch extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'produced'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status' => MeatProductionStatus::class,
            'produced_on' => 'date',
            'input_kg' => 'decimal:2',
            'output_kg' => 'decimal:2',
            'waste_kg' => 'decimal:2',
            'live_cost_minor' => 'integer',
            'other_cost_minor' => 'integer',
            'total_cost_minor' => 'integer',
            'reversed_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MeatProductionLine::class);
    }

    public function carcasses(): HasMany
    {
        return $this->hasMany(Carcass::class, 'meat_production_batch_id');
    }

    public function producedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'produced_by');
    }
}
