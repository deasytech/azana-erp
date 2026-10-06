<?php

namespace App\Domain\Slaughter\Models;

use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\System\Concerns\Auditable;
use App\Enums\CarcassStatus;
use App\Enums\PostMortemResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carcass extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'hanging', 'condemned_kg' => 0];

    protected function casts(): array
    {
        return [
            'status' => CarcassStatus::class,
            'post_mortem' => PostMortemResult::class,
            'slaughtered_at' => 'datetime',
            'live_weight_kg' => 'decimal:2',
            'hot_weight_kg' => 'decimal:2',
            'dressing_percent' => 'decimal:2',
            'condemned_kg' => 'decimal:2',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SlaughterRecord::class, 'slaughter_record_id');
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(MeatProductionBatch::class, 'meat_production_batch_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(CarcassAdjustment::class);
    }

    public function slaughteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'slaughtered_by');
    }

    /** Weight that can still be made into meat: hot weight less what the inspector condemned. */
    public function usableKg(): string
    {
        return $this->status === CarcassStatus::Condemned ? '0.00' : bcsub((string) $this->hot_weight_kg, (string) $this->condemned_kg, 2);
    }
}
