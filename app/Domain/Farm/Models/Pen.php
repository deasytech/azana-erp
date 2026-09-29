<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Farm\Concerns\ValidatesLocationHierarchy;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pen extends Model
{
    use Auditable, HasBusinessCode, ValidatesLocationHierarchy;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'capacity' => 'integer'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'purpose_id');
    }

    /** Extended in Phase 04 once animals/movements reference pens. */
    public function isInUse(): bool
    {
        return false;
    }
}
