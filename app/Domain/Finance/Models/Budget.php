<?php

namespace App\Domain\Finance\Models;

use App\Domain\System\Concerns\Auditable;
use App\Enums\BudgetStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => BudgetStatus::class, 'fiscal_year' => 'integer', 'approved_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === BudgetStatus::Approved;
    }
}
