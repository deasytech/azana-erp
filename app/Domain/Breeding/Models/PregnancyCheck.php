<?php

namespace App\Domain\Breeding\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\PregnancyCheckMethod;
use App\Enums\PregnancyCheckResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PregnancyCheck extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['checked_on' => 'date', 'result' => PregnancyCheckResult::class, 'method' => PregnancyCheckMethod::class];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(BreedingService::class, 'breeding_service_id');
    }
}
