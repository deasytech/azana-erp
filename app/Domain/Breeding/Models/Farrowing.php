<?php

namespace App\Domain\Breeding\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Litter\Models\Litter;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Farrowing extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['farrowed_on' => 'date', 'assisted' => 'boolean', 'total_birth_weight_kg' => 'decimal:2'];
    }

    public function sow(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sow_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(BreedingService::class, 'breeding_service_id');
    }

    public function litter(): HasOne
    {
        return $this->hasOne(Litter::class);
    }
}
