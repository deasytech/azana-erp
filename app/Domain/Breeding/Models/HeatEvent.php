<?php

namespace App\Domain\Breeding\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeatEvent extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['detected_on' => 'date'];
    }

    public function sow(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sow_id');
    }
}
