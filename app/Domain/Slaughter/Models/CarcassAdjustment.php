<?php

namespace App\Domain\Slaughter\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarcassAdjustment extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['old_hot_weight_kg' => 'decimal:2', 'new_hot_weight_kg' => 'decimal:2'];
    }

    public function carcass(): BelongsTo
    {
        return $this->belongsTo(Carcass::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
