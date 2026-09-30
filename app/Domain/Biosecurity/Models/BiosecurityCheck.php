<?php

namespace App\Domain\Biosecurity\Models;

use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use App\Support\Ratio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiosecurityCheck extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
        ];
    }

    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class, 'production_unit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BiosecurityCheckItem::class, 'biosecurity_check_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scorePercent(): ?string
    {
        return Ratio::percent($this->items_passed, $this->items_total);
    }
}
