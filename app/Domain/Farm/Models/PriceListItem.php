<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['unit_price_minor' => 'integer'];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function unitPrice(): Money
    {
        return Money::ofMinor($this->unit_price_minor, $this->priceList->currency_code);
    }

    public function isInUse(): bool
    {
        return false;
    }
}
