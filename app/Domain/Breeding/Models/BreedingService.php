<?php

namespace App\Domain\Breeding\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\ServiceMethod;
use App\Enums\ServiceOutcome;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BreedingService extends Model
{
    use Auditable, ImmutableRecord;

    protected $guarded = [];

    protected $attributes = ['outcome' => 'pending'];

    /** Only the result of the service changes after the fact; the service itself is a permanent record. */
    public function mutableColumns(): array
    {
        return ['outcome', 'outcome_on', 'updated_at'];
    }

    protected function casts(): array
    {
        return [
            'method' => ServiceMethod::class,
            'outcome' => ServiceOutcome::class,
            'serviced_on' => 'date',
            'outcome_on' => 'date',
            'expected_pregnancy_check_on' => 'date',
            'expected_farrowing_on' => 'date',
            'expected_weaning_on' => 'date',
            'expected_next_heat_on' => 'date',
            'expected_next_service_on' => 'date',
        ];
    }

    public function sow(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sow_id');
    }

    public function semenBatch(): BelongsTo
    {
        return $this->belongsTo(SemenBatch::class);
    }

    public function boar(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'boar_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(PregnancyCheck::class)->orderBy('checked_on');
    }

    public function technicianLabel(): ?string
    {
        return $this->technician?->name ?? $this->technician_name;
    }
}
