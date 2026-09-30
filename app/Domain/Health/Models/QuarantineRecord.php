<?php

namespace App\Domain\Health\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\QuarantineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarantineRecord extends Model
{
    use Auditable, ImmutableRecord;

    protected $guarded = [];

    /** Only these columns may change after creation. */
    public function mutableColumns(): array
    {
        return ['released_on', 'released_by', 'release_notes', 'updated_at'];
    }

    protected function casts(): array
    {
        return [
            'type' => QuarantineType::class,
            'started_on' => 'date',
            'released_on' => 'date',
        ];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function isOpen(): bool
    {
        return $this->released_on === null;
    }
}
