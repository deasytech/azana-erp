<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneticLine extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    /** Extended in Phase 04 once animals reference genetic lines. */
    public function isInUse(): bool
    {
        return false;
    }
}
