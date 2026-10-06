<?php

namespace App\Domain\Semen\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Concerns\Auditable;
use App\Enums\SemenBoarStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemenBoar extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['status' => SemenBoarStatus::class, 'min_interval_days' => 'integer', 'target_doses_per_week' => 'integer'];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function isInUse(): bool
    {
        return SemenCollection::where('animal_id', $this->animal_id)->exists();
    }
}
