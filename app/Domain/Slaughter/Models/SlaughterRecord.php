<?php

namespace App\Domain\Slaughter\Models;

use App\Domain\Animal\Models\Animal;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\System\Concerns\Auditable;
use App\Enums\AnteMortemResult;
use App\Enums\SlaughterRecordStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SlaughterRecord extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'received', 'heads' => 1];

    protected function casts(): array
    {
        return [
            'status' => SlaughterRecordStatus::class,
            'ante_mortem' => AnteMortemResult::class,
            'received_at' => 'datetime',
            'live_weight_kg' => 'decimal:2',
            'live_cost_minor' => 'integer',
            'heads' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SlaughterBatch::class, 'slaughter_batch_id');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function carcass(): HasOne
    {
        return $this->hasOne(Carcass::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** What the record is about, for display: the animal's number or the batch the pigs came from. */
    public function sourceLabel(): string
    {
        return $this->animal?->animal_number ?? "{$this->heads} pigs from {$this->productionBatch?->code}";
    }
}
