<?php

namespace App\Domain\Semen\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SemenCollection extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime', 'volume_ml' => 'decimal:1', 'ph' => 'decimal:1'];
    }

    public function boar(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'animal_id');
    }

    public function batch(): HasOne
    {
        return $this->hasOne(SemenBatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
