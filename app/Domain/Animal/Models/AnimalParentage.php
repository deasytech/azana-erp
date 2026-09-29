<?php

namespace App\Domain\Animal\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalParentage extends Model
{
    use Auditable;

    protected $table = 'animal_parentage';

    protected $guarded = [];

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sire_id');
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'dam_id');
    }
}
