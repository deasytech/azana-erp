<?php

namespace App\Domain\Animal\Models;

use App\Domain\System\Concerns\Auditable;
use App\Enums\IdentifierType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalIdentifier extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => IdentifierType::class, 'issued_on' => 'date', 'retired_at' => 'datetime'];
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }
}
