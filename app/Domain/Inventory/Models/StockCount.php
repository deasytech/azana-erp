<?php

namespace App\Domain\Inventory\Models;

use App\Domain\System\Concerns\Auditable;
use App\Enums\StockCountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'counted_on' => 'date',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
