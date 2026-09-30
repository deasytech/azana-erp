<?php

namespace App\Domain\Health\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaccinationSchedule extends Model
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

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'category_id');
    }

    public function isInUse(): bool
    {
        return Vaccination::where('vaccination_schedule_id', $this->id)->exists();
    }
}
