<?php

namespace App\Domain\Health\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\HealthEventKind;
use App\Enums\HealthEventStatus;
use App\Enums\HealthSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthEvent extends Model
{
    use Auditable, ImmutableRecord;

    protected $guarded = [];

    protected $attributes = ['status' => 'open'];

    /** Only these columns may change after creation. */
    public function mutableColumns(): array
    {
        return ['status', 'resolved_on', 'resolution_notes', 'updated_at'];
    }

    protected function casts(): array
    {
        return [
            'kind' => HealthEventKind::class,
            'severity' => HealthSeverity::class,
            'status' => HealthEventStatus::class,
            'observed_on' => 'date',
            'resolved_on' => 'date',
        ];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VeterinaryVisit::class, 'veterinary_visit_id');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === HealthEventStatus::Open;
    }
}
