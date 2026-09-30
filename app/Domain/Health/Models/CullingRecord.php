<?php

namespace App\Domain\Health\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\CullHealthStatus;
use App\Enums\DisposalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CullingRecord extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'culled_on' => 'date',
            'weight_kg' => 'decimal:2',
            'performance' => 'array',
            'health_status' => CullHealthStatus::class,
            'disposal' => DisposalType::class,
            'disposal_value_minor' => 'integer',
        ];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'reason_id');
    }
}
