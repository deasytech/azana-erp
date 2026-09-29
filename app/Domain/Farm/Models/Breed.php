<?php

namespace App\Domain\Farm\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Breed extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function geneticLines(): HasMany
    {
        return $this->hasMany(GeneticLine::class);
    }

    public function isInUse(): bool
    {
        return $this->geneticLines()->exists() || Animal::where('breed_id', $this->id)->exists();
    }
}
