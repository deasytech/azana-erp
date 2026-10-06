<?php

namespace App\Domain\Semen\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\Breed;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\System\Concerns\Auditable;
use App\Enums\SemenBatchStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SemenBatch extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'pending_qc'];

    protected function casts(): array
    {
        return [
            'status' => SemenBatchStatus::class,
            'collected_on' => 'date',
            'expiry_date' => 'date',
            'doses_produced' => 'integer',
            'dose_volume_ml' => 'decimal:1',
            'processed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(SemenCollection::class, 'semen_collection_id');
    }

    public function boar(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'animal_id');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function qcRecords(): HasMany
    {
        return $this->hasMany(SemenQcRecord::class);
    }

    public function latestQc(): HasOne
    {
        return $this->hasOne(SemenQcRecord::class)->latestOfMany();
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->lt(now()->startOfDay());
    }

    /** Can doses of this batch be sold or used? Only a released batch that has not expired and is not blocked. */
    public function isSellable(): bool
    {
        return $this->status === SemenBatchStatus::Released && ! $this->isExpired() && $this->inventoryBatch?->is_active === true;
    }
}
