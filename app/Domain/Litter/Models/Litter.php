<?php

namespace App\Domain\Litter\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Breeding\Models\Farrowing;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\LitterStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Litter extends Model
{
    use Auditable, ImmutableRecord;

    protected $guarded = [];

    /** Identity and birth facts are permanent; only the weaning state advances. */
    public function mutableColumns(): array
    {
        return ['status', 'weaned_on', 'updated_at'];
    }

    protected function casts(): array
    {
        return [
            'status' => LitterStatus::class,
            'born_on' => 'date',
            'expected_weaning_on' => 'date',
            'weaned_on' => 'date',
        ];
    }

    public function sow(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sow_id');
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sire_id');
    }

    public function farrowing(): BelongsTo
    {
        return $this->belongsTo(Farrowing::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(BreedingService::class, 'breeding_service_id');
    }

    public function piglets(): HasMany
    {
        return $this->hasMany(Piglet::class);
    }

    public function losses(): HasMany
    {
        return $this->hasMany(LitterLoss::class)->orderBy('occurred_on');
    }

    public function weaning(): HasOne
    {
        return $this->hasOne(WeaningRecord::class);
    }

    public function isSuckling(): bool
    {
        return $this->status === LitterStatus::Suckling;
    }
}
