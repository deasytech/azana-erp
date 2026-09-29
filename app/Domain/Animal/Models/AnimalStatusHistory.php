<?php

namespace App\Domain\Animal\Models;

use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\AnimalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalStatusHistory extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $table = 'animal_status_history';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['from_status' => AnimalStatus::class, 'to_status' => AnimalStatus::class, 'changed_at' => 'datetime'];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
