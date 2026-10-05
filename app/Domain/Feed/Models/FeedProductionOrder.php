<?php

namespace App\Domain\Feed\Models;

use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\System\Concerns\Auditable;
use App\Enums\FeedProductionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FeedProductionOrder extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return [
            'status' => FeedProductionStatus::class,
            'planned_output_kg' => 'decimal:3',
            'planned_on' => 'date',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(FeedFormula::class, 'feed_formula_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FeedProductionOrderLine::class);
    }

    public function batch(): HasOne
    {
        return $this->hasOne(FeedProductionBatch::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function outputLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'output_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
