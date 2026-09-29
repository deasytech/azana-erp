<?php

namespace App\Domain\Animal\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalPhoto extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['taken_on' => 'date'];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}
