<?php

namespace App\Domain\Slaughter\Models;

use App\Domain\System\Concerns\Auditable;
use App\Enums\SlaughterBatchStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaughterBatch extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'scheduled'];

    protected function casts(): array
    {
        return ['status' => SlaughterBatchStatus::class, 'scheduled_on' => 'date'];
    }

    public function records(): HasMany
    {
        return $this->hasMany(SlaughterRecord::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === SlaughterBatchStatus::Scheduled || $this->status === SlaughterBatchStatus::InProgress;
    }
}
