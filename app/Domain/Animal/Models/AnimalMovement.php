<?php

namespace App\Domain\Animal\Models;

use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalMovement extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['moved_at' => 'datetime'];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function fromPen(): BelongsTo
    {
        return $this->belongsTo(Pen::class, 'from_pen_id');
    }

    public function toPen(): BelongsTo
    {
        return $this->belongsTo(Pen::class, 'to_pen_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'reason_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromLabel(): string
    {
        return $this->fromPen?->code ?? $this->fromLocation?->name ?? 'Outside the farm';
    }

    public function toLabel(): string
    {
        return $this->toPen?->code ?? $this->toLocation?->name ?? 'Left the farm';
    }
}
