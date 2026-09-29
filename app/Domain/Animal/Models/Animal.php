<?php

namespace App\Domain\Animal\Models;

use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\GeneticLine;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Litter\Models\Litter;
use App\Domain\System\Concerns\Auditable;
use App\Enums\AnimalSex;
use App\Enums\AnimalSource;
use App\Enums\AnimalStatus;
use App\Support\Money;
use Database\Factories\AnimalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class Animal extends Model
{
    use Auditable, HasFactory;

    protected $guarded = [];

    protected static function newFactory(): AnimalFactory
    {
        return AnimalFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $animal) {
            $animal->public_id ??= (string) Str::ulid();
            $animal->status ??= AnimalStatus::Active;
            $animal->status_changed_at ??= now();
        });

        // The permanent identification number never changes and animals are never deleted.
        static::updating(function (self $animal) {
            if ($animal->isDirty('animal_number') || $animal->isDirty('public_id')) {
                throw new LogicException('An animal\'s permanent identifiers cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Animals cannot be deleted; change their status instead.'));
    }

    protected function casts(): array
    {
        return [
            'sex' => AnimalSex::class,
            'source' => AnimalSource::class,
            'status' => AnimalStatus::class,
            'birth_date' => 'date',
            'acquired_on' => 'date',
            'status_changed_at' => 'datetime',
            'birth_date_estimated' => 'boolean',
            'purchase_price_minor' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'category_id');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function geneticLine(): BelongsTo
    {
        return $this->belongsTo(GeneticLine::class);
    }

    public function currentPen(): BelongsTo
    {
        return $this->belongsTo(Pen::class, 'current_pen_id');
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(AnimalIdentifier::class);
    }

    public function parentage(): HasOne
    {
        return $this->hasOne(AnimalParentage::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AnimalPhoto::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AnimalMovement::class)->orderByDesc('moved_at')->orderByDesc('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AnimalStatusHistory::class)->orderByDesc('changed_at')->orderByDesc('id');
    }

    public function litters(): HasMany
    {
        return $this->hasMany(Litter::class, 'sow_id')->orderByDesc('born_on');
    }

    public function weights(): HasMany
    {
        return $this->hasMany(WeightRecord::class)->orderByDesc('weighed_at')->orderByDesc('id');
    }

    /** Latest valid (non-voided) weight. */
    public function latestWeight(): ?WeightRecord
    {
        return $this->weights()->whereNull('voided_at')->first();
    }

    /** Sow or gilt: a female that can be served and farrow. */
    public function isBreedingFemale(): bool
    {
        return $this->sex === AnimalSex::Female && in_array($this->category?->code, ['sow', 'gilt'], true);
    }

    public function isBoar(): bool
    {
        return $this->sex === AnimalSex::Male && $this->category?->code === 'boar';
    }

    public function isActive(): bool
    {
        return $this->status === AnimalStatus::Active;
    }

    /** Value encoded in the animal's QR code / barcode label. */
    public function qrPayload(): string
    {
        return route('animals.lookup', ['code' => $this->public_id]);
    }

    public function purchasePrice(?string $currency = null): ?Money
    {
        return $this->purchase_price_minor === null ? null : Money::ofMinor($this->purchase_price_minor, $currency ?? 'NGN');
    }

    /** Human-readable current position ("Pen X (Building Y)"). */
    public function positionLabel(): string
    {
        if ($this->currentPen) {
            return $this->currentPen->code.' ('.$this->currentPen->building->name.')';
        }

        return $this->currentLocation?->name ?? '-';
    }
}
