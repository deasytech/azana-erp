<?php

namespace App\Domain\Biosecurity\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiosecurityVisit extends Model
{
    use Auditable, ImmutableRecord;

    protected $guarded = [];

    /** Only these columns may change after creation. */
    public function mutableColumns(): array
    {
        return ['departed_at', 'updated_at'];
    }

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'departed_at' => 'datetime',
            'health_declaration' => 'boolean',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
