<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farm extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** The currency money is shown in: the first active farm's, or NGN before any farm exists. */
    public static function defaultCurrency(): string
    {
        return static::where('is_active', true)->orderBy('id')->value('currency_code') ?? 'NGN';
    }

    public function productionUnits(): HasMany
    {
        return $this->hasMany(ProductionUnit::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(FarmSetting::class);
    }

    public function isInUse(): bool
    {
        return $this->productionUnits()->exists() || $this->settings()->exists() || PriceList::where('farm_id', $this->id)->exists();
    }
}
