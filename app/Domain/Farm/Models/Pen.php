<?php

namespace App\Domain\Farm\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalMovement;
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

    public function isInUse(): bool
    {
        return Animal::where('current_pen_id', $this->id)->exists()
            || AnimalMovement::where('from_pen_id', $this->id)->orWhere('to_pen_id', $this->id)->exists();
    }
}
