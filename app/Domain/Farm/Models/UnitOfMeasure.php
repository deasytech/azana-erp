<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitOfMeasure extends Model
{
    use Auditable, HasBusinessCode;

    protected $table = 'units_of_measure';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }

    /**
     * Convert a quantity (decimal string) in this unit to the given unit of the same category.
     * Uses bcmath-free string arithmetic via bc when available; quantities stay strings.
     */
    public function convertTo(string $quantity, self $target): string
    {
        if ($this->is($target)) {
            return $quantity;
        }

        $from = $this->toBaseFactor();
        $to = $target->toBaseFactor();

        if ($this->rootId() !== $target->rootId()) {
            throw new DomainException("Cannot convert {$this->code} to {$target->code}: different measurement families.", 'unit_conversion');
        }

        return bcdiv(bcmul($quantity, $from, 12), $to, 8);
    }

    private function toBaseFactor(): string
    {
        return $this->base_unit_id ? (string) $this->conversion_factor : '1';
    }

    private function rootId(): int
    {
        return $this->base_unit_id ?? $this->id;
    }

    /** Extended when items/prices reference units. */
    public function isInUse(): bool
    {
        return self::where('base_unit_id', $this->id)->exists() || PriceListItem::where('unit_id', $this->id)->exists();
    }
}
