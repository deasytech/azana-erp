<?php

namespace App\Domain\Health\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'type_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function isVaccine(): bool
    {
        return $this->type?->code === 'vaccine';
    }

    public function isInUse(): bool
    {
        return Treatment::where('medicine_id', $this->id)->exists() || Vaccination::where('medicine_id', $this->id)->exists() || VaccinationSchedule::where('medicine_id', $this->id)->exists() || $this->batches()->exists();
    }
}
