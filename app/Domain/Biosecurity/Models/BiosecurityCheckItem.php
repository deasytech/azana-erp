<?php

namespace App\Domain\Biosecurity\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiosecurityCheckItem extends Model
{
    use Auditable, ImmutableRecord;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
        ];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(BiosecurityCheck::class, 'biosecurity_check_id');
    }
}
