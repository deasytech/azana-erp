<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
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
        return $this->pens()->exists() || $this->locations()->exists();
    }
}
