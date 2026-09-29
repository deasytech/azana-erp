<?php

namespace App\Domain\Animal\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightRecord extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weighed_at' => 'datetime', 'voided_at' => 'datetime', 'weight_kg' => 'decimal:2'];
    }

    /** Wrong readings are voided (kept, flagged, audited) rather than edited or deleted. */
    public function mutableColumns(): array
    {
        return ['voided_at', 'voided_by', 'void_reason'];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
