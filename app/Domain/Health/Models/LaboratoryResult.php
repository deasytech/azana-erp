<?php

namespace App\Domain\Health\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryResult extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $table = 'laboratory_results';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sampled_on' => 'date',
            'resulted_on' => 'date',
            'is_abnormal' => 'boolean',
        ];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}
