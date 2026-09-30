<?php

namespace App\Domain\Litter\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeaningRecord extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weaned_on' => 'date', 'expected_next_service_on' => 'date', 'total_weight_kg' => 'decimal:2'];
    }

    public function litter(): BelongsTo
    {
        return $this->belongsTo(Litter::class);
    }
}
