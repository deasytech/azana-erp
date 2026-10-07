<?php

namespace App\Domain\Sales\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Meat\Models\MeatProductionLine;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\SalesLineKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => SalesLineKind::class,
            'quantity' => 'decimal:3',
            'unit_price_minor' => 'integer',
            'discount_percent' => 'decimal:2',
            'line_total_minor' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function semenBatch(): BelongsTo
    {
        return $this->belongsTo(SemenBatch::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function meatLine(): BelongsTo
    {
        return $this->belongsTo(MeatProductionLine::class, 'meat_production_line_id');
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }
}
