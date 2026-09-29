<?php

namespace App\Domain\Farm\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalMovement;
use App\Domain\System\Concerns\Auditable;
use App\Enums\LookupCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class LookupValue extends Model
{
    use Auditable;

    protected $guarded = [];

    /** Lookup codes are lower snake_case (unlike upper-case business codes elsewhere). */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'category' => LookupCategory::class];
    }

    public function scopeInCategory(Builder $query, LookupCategory $category): Builder
    {
        return $query->where('category', $category->value)->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function isInUse(): bool
    {
        return ProductionUnit::where('type_id', $this->id)->exists()
            || Building::where('type_id', $this->id)->exists()
            || Location::where('type_id', $this->id)->exists()
            || Pen::where('purpose_id', $this->id)->exists()
            || PriceList::where('category_id', $this->id)->exists()
            || Animal::where('category_id', $this->id)->exists()
            || AnimalMovement::where('reason_id', $this->id)->exists();
    }
}
