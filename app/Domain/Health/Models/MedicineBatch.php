<?php

namespace App\Domain\Health\Models;

use App\Domain\System\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineBatch extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function isExpiredOn(CarbonInterface $date): bool
    {
        return $this->expiry_date->lt($date->copy()->startOfDay());
    }

    public function isInUse(): bool
    {
        return Treatment::where('medicine_batch_id', $this->id)->exists() || Vaccination::where('medicine_batch_id', $this->id)->exists();
    }
}
