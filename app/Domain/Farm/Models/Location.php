<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Farm\Concerns\ValidatesLocationHierarchy;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use Auditable, HasBusinessCode, ValidatesLocationHierarchy;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'type_id');
    }

    /** Extended in Phase 08 once inventory references locations. */
    public function isInUse(): bool
    {
        return false;
    }
}
