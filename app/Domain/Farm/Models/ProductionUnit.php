<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionUnit extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'type_id');
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function isInUse(): bool
    {
        return $this->buildings()->exists() || $this->locations()->exists();
    }
}
