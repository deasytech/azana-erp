<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'type_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function pens(): HasMany
    {
        return $this->hasMany(Pen::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function isInUse(): bool
    {
        return $this->rooms()->exists() || $this->pens()->exists() || $this->locations()->exists();
    }
}
